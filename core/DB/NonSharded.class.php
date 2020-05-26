<?php
/**
 * @author Mikhail Kulakovskiy <m@klkvsk.ru>
 * @date 2017-08-29
 */

/**
 * Ranged sharding
 * only works with integer sharding key
 */
class NonSharded implements ShardingStrategy
{
    /** @var string */
    protected $tableName;
    protected $shardId;

    public function __construct($tableName, $shardId)
    {
        $this->tableName = $tableName;
        $this->shardId = $shardId;
    }


    public function targetizeSelectQuery(SelectQuery $query, $shardId)
    {
        if ($shardId != $this->shardId) {
            throw new WrongStateException('wrong shard id');
        }

        return $query;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getShardIdsByWhereClause(QuerySkeleton $query)
    {
        return [ $this->shardId ];
    }

    public function getShardIdByFieldValue(InsertOrUpdateQuery $query)
    {
        return $this->shardId;
    }

    public function chooseShard($value)
    {
        return $this->shardId;
    }

    public function chooseShards(Range $values)
    {
        return [ $this->shardId ];
    }

    public function getShardingKey()
    {
        return null;
    }

}