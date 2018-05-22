<?php
/**
 * @author Mikhail Kulakovskiy <m@klkvsk.ru>
 * @date 2017-08-29
 *
 * Implements simple sharding composition of several other databases
 *
 *
 * usage:
 *   $shardedDb = new ShardedDB();
 *   $shardedDb->addShard(1, DBPool::me()->getLink('users_A'));
 *   $shardedDb->addShard(2, DBPool::me()->getLink('users_B));
 *   $shardedDb->setShardedTable(
 *     new RangedSharding('users', 'id', [
 *         1 => Range::create(0,    1000),
 *         2 => Range::create(1001, 9999),
 *     ])
 *   );
 *   DBPool::me()->addLink('users_sharded', $shardedDb);
 */

class ShardedDB extends MultiDB
{
    /** @var DBInterface[] */
    protected $shards = [];
    /** @var ShardingStrategy[] */
    protected $shardingStrategies = [];

    /**
     * @param $id
     * @param DBInterface $db
     * @return $this
     */
    public function addShard($id, DBInterface $db)
    {
        $this->shards[$id] = $db;
        return $this;
    }

    /**
     * @return DBInterface[]
     */
    public function getShards()
    {
        return $this->shards;
    }

    public function getShard($id)
    {
        if (!isset($this->shards[$id])) {
            throw new MissingElementException('shard ' . $id . ' not found');
        }

        return $this->shards[$id];
    }

    /**
     * @param ShardingStrategy $shardingStrategy
     * @return $this
     */
    public function setShardedTable(ShardingStrategy $shardingStrategy)
    {
        $this->shardingStrategies[$shardingStrategy->getTableName()] = $shardingStrategy;
        return $this;
    }

    /**
     * @param $tableName
     * @return ShardingStrategy
     */
    public function getShardedTable($tableName)
    {
        Assert::isIndexExists($this->shardingStrategies, $tableName, 'no sharding set up for ' .$tableName);
        return $this->shardingStrategies[$tableName];
    }

    /**
     * @param Query $query
     * @return DBInterface[]
     * @throws UnimplementedFeatureException
     * @throws WrongArgumentException
     */
    public function getShardsForQuery(Query $query)
    {
        $strategy = $this->getShardingStrategy($query);

        if ($query instanceof SelectQuery || $query instanceof DeleteQuery) {
            $shardIds = $strategy->getShardIdsByWhereClause($query);
        } else if ($query instanceof InsertOrUpdateQuery) {
            try {
                $shardIds = [ $strategy->getShardIdByFieldValue($query) ];
            } catch (WrongArgumentException $e) {
                if ($query instanceof UpdateQuery) {
                    $shardIds = $strategy->getShardIdsByWhereClause($query);
                } else {
                    throw $e;
                }
            }
        } else {
            throw new UnimplementedFeatureException('can not deal with ' . get_class($query));
        }

        if (empty($shardIds)) {
            throw new WrongArgumentException('unable to route query: ' . $query->toDialectString($this->getDialect()));
        }

        $shards = [];
        foreach ($shardIds as  $id) {
            $shards[$id] = $this->getShard($id);
        }

        return $shards;
    }

    public function queryRaw($queryString)
    {
        if (!preg_match('/^set /i', $queryString)) {
            throw new UnimplementedFeatureException('can not parse raw query string');
        }
        foreach ($this->getShards() as $shard) {
            $shard->queryRaw($queryString);
        }
    }

    public function query(Query $query)
    {
        throw new UnimplementedFeatureException('not possible to return single resource for multiple db requests');
    }

    public function queryColumn(Query $query)
    {
        assert($query instanceof SelectQuery,
            'only SELECT queries are supported'
        );
        $set = $this->runSelectQuery($query);
        return array_column($set, 0);
    }

    public function queryRow(Query $query)
    {
        assert($query instanceof SelectQuery,
            'only SELECT queries are supported'
        );
        $set = $this->runSelectQuery($query);
        return $set[0];
    }

    public function querySet(Query $query)
    {
        assert($query instanceof SelectQuery,
            'only SELECT queries are supported'
        );
        return $this->runSelectQuery($query);
    }

    /**
     * Returns alias for $fieldName if it is found in select fields.
     * Otherwise returns $fieldName as is.
     * Example:
     *  in "SELECT oid AS ObjectID .. GROUP BY oid"
     *  resolveToAlias("oid") returns "ObjectID"
     * @param SelectQuery $query
     * @param $fieldName
     * @return null
     */
    protected function resolveToAlias(SelectQuery $query, $fieldName) {
        foreach ($query->getFields() as $selectField) {
            $selectFieldName = $selectField->getField();
            if ($selectFieldName instanceof DBField) {
                $selectFieldName = $selectFieldName->getField();
            }
            if ($selectFieldName == $fieldName) {
                if ($selectField->getAlias()) {
                    return $selectField->getAlias();
                }
                break;
            }
        }
        return $fieldName;
    }

    protected function runSelectQuery(SelectQuery $query)
    {
        $shards = $this->getShardsForQuery($query);
        if (count($shards) == 1) {
            return reset($shards)->querySet(
                $this->getShardingStrategy($query)->targetizeSelectQuery($query, key($shards))
            );
        }

        // store query configuration for post-processing
        /** @var bool[] $fields         keys=fields values=true */
        $fields = [];
        /** @var string[] $aggregates   keys=fields values=name of aggregation function */
        $aggregates = [];
        /** @var bool[] $groupBy        keys=fields values=true */
        $groupBy = [];
        /** @var OrderBy[] $orderBy     keys=fields values=OrderBy from original query */
        $orderBy = [];

        $limit = $query->getLimit();
        $offset = $query->getOffset();
        if ($limit || $offset) {
            // offsets are unusable with sharding, we take every row until (offset + limit) on
            // each shard, and then apply limit and offset manually
            $query->dropLimit()->limit($limit + $offset);
        }

        // reducers will be applied to merge aggregations from multiple result sets into one
        $aggregateReducers = [
            'sum'   => function ($v, $acc) { return $acc + $v; },
            'count' => function ($v, $acc) { return $acc + $v; },
            'min'   => function ($v, $acc) { return min($v, $acc); },
            'max'   => function ($v, $acc) { return max($v, $acc); },
            'avg'   => function ($v, $acc) {
                if (!is_array($acc)) {
                    $acc = [ 'sum' => $acc, 'n' => 1 ];
                }
                return [ 'sum' => $acc['sum'] + $v, 'n' => $acc['n'] + 1 ];
             },
        ];
        // some aggregations require finishing procedure
        $aggregateFinalizers = [
            'avg' => function ($acc) {
                if (!is_array($acc)) {
                    // in case there was only 1 row in group and reducer did not run
                    return $acc;
                }
                return $acc['sum'] / $acc['n'];
            }
        ];
        $needsFinalizers = false;

        // collect all fields, check if some of them are aggregations
        foreach ($query->getFields() as $selectField) {
            $field = $selectField->getField();
            if ($field instanceof SQLFunction) {
                $functionName = strtolower($field->getName());
                $functionAlias = $selectField->getAlias() ?: $field->getAlias();
                if (array_key_exists($functionName, $aggregateReducers)) {
                    assert(!empty($functionAlias), 'aggregated field should be aliased');

                    $aggregates[$functionAlias] = $functionName;
                    if (array_key_exists($functionName, $aggregateFinalizers)) {
                        $needsFinalizers = true;
                    }
                }
                $fields[$functionAlias] = true;
            } else {
                $fields[$selectField->getAlias() ?: $selectField->getName()] = true;

            }
        }

        // with aggregations, every field we do not aggregate should exist in groupBy list, double check that!
        if (!empty($aggregates)) {
            foreach ($query->getGroupBy() as $groupField) {
                switch (true) {
                    case is_string($groupField):
                        $groupFieldName = $groupField;
                        break;

                    case $groupField instanceof DBField:
                        $groupFieldName = $groupField->getField();
                        break;

                    case $groupField instanceof GroupBy:
                        $groupFieldName = $groupField->getField();
                        if ($groupFieldName instanceof DBField) {
                            // can be wrapped inside too
                            $groupFieldName = $groupFieldName->getField();
                        }
                        break;

                    default:
                        throw new WrongArgumentException(
                            'can not group by '
                            . (is_object($groupField) ? get_class($groupField) : var_export($groupField, true))
                        );
                }
                $groupFieldName = $this->resolveToAlias($query, $groupFieldName);
                $groupBy[$groupFieldName] = true;
            }


            assert(count($groupBy) + count($aggregates) == count($fields), 'probably missed some fields!');
        }

        // finally save ordering, we can use original OrderBy for later, but make sure it's sort by field, not function
        foreach ($query->getOrderChain()->getList() as $order) {
            assert($order->getField() instanceof DBField, 'can not order by ' . get_class($order->getField()));
            $orderFieldName = $this->resolveToAlias($query, $order->getFieldName());
            $orderBy[$orderFieldName] = $order;
        }

        // actually do the query and merge all rows into one set
        $result = [];
        foreach ($shards as $shardId => $shard) {
            $shardResult = $shard->querySet(
                $this->getShardingStrategy($query)->targetizeSelectQuery($query, $shardId)
            );
            foreach ($shardResult as $row) {
                $result []= $row;
            }
        }


        // if aggregations are in, do them
        if (!empty($aggregates)) {
            $groupedResult = [];
            foreach ($result as $row) {
                // collect groupBy fields as key
                $groupKey = [];
                foreach ($row as $field => $value) {
                    if (isset($groupBy[$field])) {
                        $groupKey []= $value;
                    }
                }
                $groupKey = serialize($groupKey);

                // first row in group go as-is
                if (!isset($groupedResult[$groupKey])) {
                    $groupedResult[$groupKey] = $row;
                    continue;
                }

                // other rows are merged in with reducers
                foreach ($aggregates as $aggregateField => $aggregateFunctionName) {
                    $groupedResult[$groupKey][$aggregateField] = $aggregateReducers[$aggregateFunctionName](
                        $row[$aggregateField],
                        $groupedResult[$groupKey][$aggregateField]
                    );
                }
            }

            // run finishing functions
            if ($needsFinalizers) {
                foreach ($groupedResult as &$row) {
                    foreach ($aggregates as $aggregateField => $aggregateFunctionName) {
                        if (array_key_exists($aggregateFunctionName, $aggregateFinalizers)) {
                            $row[$aggregateField] = $aggregateFinalizers[$aggregateFunctionName](
                                $row[$aggregateField]
                            );
                        }
                    }
                }
            }

            // use result of grouping
            $result = array_values($groupedResult);
        }

        // next we do the sorting
        usort($result, function ($rowA, $rowB) use ($query, $orderBy) {
            foreach ($orderBy as $order) {
                $fieldName = $this->resolveToAlias($query, $order->getFieldName());
                $fieldA = $rowA[$fieldName];
                $fieldB = $rowB[$fieldName];
                if ($fieldA === null || $fieldB === null) {
                    if ($fieldA === null && $fieldB === null) {
                        continue;
                    }
                    if ($fieldA === null) {
                        return $order->isNullsFirst() ? -1 : 1;
                    }
                    if ($fieldB === null) {
                        return $order->isNullsFirst() ? 1 : -1;
                    }
                }
                if ($fieldA > $fieldB) {
                    return $order->isAsc() ? 1 : -1;
                }
                if ($fieldA < $fieldB) {
                    return $order->isAsc() ? -1 : 1;
                }
            }
            return 0;
        });

        // finally do the limiting
        if ($offset || $limit) {
            $result = array_slice($result, $offset, $limit);
        }

        return $result;
    }

    public function queryNumRows(Query $query)
    {
        assert($query instanceof SelectQuery,
            'only SelectQuery are supported'
        );
        $num = 0;
        foreach ($this->getShardsForQuery($query) as $shard) {
            $num += $shard->queryNumRows($query);
        }
        return $num;
    }


    public function queryCount(Query $query)
    {
        assert($query instanceof InsertOrUpdateQuery || $query instanceof DeleteQuery,
            'only INSERT, UPDATE and DELETE queries are supported'
        );

        $count = 0;
        foreach ($this->getShardsForQuery($query) as $shard) {
            $count += $shard->queryCount($query);
        }
        return $count;
    }

    public function queryNull(Query $query)
    {
        assert(!($query instanceof SelectQuery),
            'only non-SELECT queries are supported'
        );

        foreach ($this->getShardsForQuery($query) as $shard) {
            $shard->queryNull($query);
        }
    }

    protected function getEndpoints()
    {
        return $this->getShards();
    }

    protected function getDefaultEndpoint()
    {
        $shards = $this->getShards();
        return reset($shards);
    }

    /**
     * @param Query $query
     * @return ShardingStrategy
     * @throws MissingElementException
     * @throws WrongArgumentException
     */
    protected function getShardingStrategy(Query $query)
    {
        if ($query instanceof SelectQuery) {
            $tableName = $query->getFirstTable();
        } else if ($query instanceof SQLTableName) {
            $tableName = $query->getTable();
        } else {
            throw new WrongArgumentException('unknown table of operation');
        }

        if (!isset($this->shardingStrategies[$tableName])) {
            throw new MissingElementException('i dont have sharding strategy for table "' . $tableName . '"');
        }

        return $this->shardingStrategies[$tableName];
    }
}