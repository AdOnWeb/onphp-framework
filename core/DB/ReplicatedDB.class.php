<?php
/**
 * @author Mikhail Kulakovskiy <m@klkvsk.ru>
 * @date 2017-08-23
 */

class ReplicatedDB extends MultiDB
{
    const READ_STRATEGY_ANY = 1;
    const READ_STRATEGY_PRIMARY = 2;
    const READ_STRATEGY_MIRRORS = 3;

    /** @var DBInterface|null  */
    protected $primary = null;
    /** @var DBInterface[] */
    protected $mirrors = [];
    /** @var int  */
    protected $pointer = -1;
    /** @var int  */
    protected $readStrategy = self::READ_STRATEGY_ANY;

    /**
     * @param $readStrategy
     * @return $this
     */
    public function setReadStrategy($readStrategy)
    {
        $this->readStrategy = $readStrategy;
        return $this;
    }

    /**
     * @return int
     */
    public function getReadStrategy()
    {
        return $this->readStrategy;
    }

    /**
     * @param DBInterface $db
     * @return $this
     */
    public function setPrimary(DBInterface $db)
    {
        $this->primary = $db;
        return $this;
    }

    /**
     * @return DBInterface|null
     */
    public function getPrimary()
    {
        return $this->primary;
    }

    /**
     * @param DBInterface $db
     * @return $this
     */
    public function addMirror(DBInterface $db)
    {
        $this->mirrors []= $db;
        return $this;
    }

    /**
     * @return DBInterface[]
     */
    public function getMirrors()
    {
        return $this->mirrors;
    }

    /**
     * @return DBInterface[]
     */
    public function getAllDbs()
    {
        return array_merge(
            [ $this->getPrimary() ],
            $this->getMirrors()
        );
    }

    /**
     * @param bool $advance
     * @return DBInterface
     * @throws UnimplementedFeatureException
     */
    public function getNextDb($advance = true)
    {
        $this->assertEndpoints();

        /**
         * ANY and MIRRORS share this round-robin logic
         * @param array $dbs
         * @return DBInterface
         */
        $rotate = function (array $dbs) use ($advance) {
            if (!isset($dbs[$this->pointer])) {
                $this->pointer = rand(0, count($dbs));
                return $this->getNextDb($advance);
            }
            $db = $dbs[$this->pointer];
            if ($advance) {
                $this->pointer = rand(0, count($dbs));
            }
            return $db;
        };

        switch ($this->readStrategy) {
            case self::READ_STRATEGY_ANY:
                return $rotate($this->getAllDbs());

            case self::READ_STRATEGY_PRIMARY:
                return $this->getPrimary();

            case self::READ_STRATEGY_MIRRORS:
                return $rotate($this->getMirrors());

            default:
                throw new UnimplementedFeatureException('unknown readStrategy=' . $this->readStrategy);
        }
    }

    public function queryRaw($queryString)
    {
        $this->assertEndpoints();

        if (preg_match('/^select/i', $queryString)) {
            $db = $this->getNextDb();
            return $db->queryRaw($queryString);
        }

        $res = null;
        foreach ($this->mirrors as $db) {
            $res = $db->queryRaw($queryString);
        }

        return $res;
    }

    public function query(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function queryRow(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function querySet(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function queryNumRows(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function queryColumn(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function queryCount(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    public function queryNull(Query $query)
    {
        return $this->proxyQuery($query, __FUNCTION__);
    }

    protected function proxyQuery(Query $query, $methodName) {
        $this->assertEndpoints();

        if ($query instanceof SelectQuery) {
            $db = $this->getNextDb();
            return $db->{$methodName}($query);
        }

        $res = null;
        foreach ($this->getAllDbs() as $db) {
            $res = $db->{$methodName}($query);
        }

        return $res;
    }

    public function fullsyncTable($tableName, $onlySchema = false, ProgressReporter $progress = null) {
        assert($this->getTableLocker() !== null, 'locker should be set');

        if (!$progress) {
            $progress = new NullProgressReporter();
        }

        if (!$this->getTableLocker()->get($tableName)) {
            throw new WrongStateException($tableName . ' is already being replicated');
        }

        try {
            $table = $this->getTableInfo($tableName);

            foreach ($this->getMirrors() as $db) {
                $db->query(OSQL::dropTable($tableName, false, true));
                $db->queryRaw($table->toDialectString($this->getDialect()));
            }

            if ($onlySchema) {
                return;
            }

            $selectQuery = OSQL::select()
                ->from($tableName)
                ->orderBy('id');
            foreach ($table->getColumns() as $column) {
                $selectQuery->get($column->getName());
            }

            $totalRows = $this->getPrimary()->queryRow(
                OSQL::select()
                    ->from($tableName)
                    ->get(SQLFunction::create('count', 'id'), 'count')
            );

            $totalRows = $totalRows['count'];
            $blockSize = 1000;
            $progress->setTotal($totalRows);
            for ($i = 0; $i < $totalRows; $i += $blockSize) {
                $progress->update($i, 'mirroring ' . $i . '-' . ($i + $blockSize));

                $block = $this->getPrimary()->querySet(
                    $selectQuery->limit($blockSize, $i)
                );

                $progress->update($i, 'fetched block');

                foreach ($block as $j => $row) {
                    $insertQuery = OSQL::insert()
                        ->into($tableName)
                        ->arraySet($row);

                    foreach ($this->getMirrors() as $mirror) {
                        $insertedRows = $mirror->queryCount($insertQuery);
                        if ($insertedRows != 1) {
                            throw new WrongStateException(
                                $insertedRows.' rows affected: racy or insane inject happened: '
                                .$this->getDialect()->queryToString($insertQuery)
                            );
                        }
                    }

                    $progress->update($i + $j, 'inserting');
                }
            }

        } finally {
            $this->getTableLocker()->free($tableName);
        }
    }

    protected function getEndpoints()
    {
        return $this->getAllDbs();
    }

    protected function getDefaultEndpoint()
    {
        return $this->getPrimary();
    }

}