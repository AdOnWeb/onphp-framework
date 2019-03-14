<?php

class Migrator implements \Psr\Log\LoggerAwareInterface
{
    protected $migrations;
    protected $linkName;
    protected $migrationsTable;
    protected $logger;

    public function __construct($linkName, $migrationsTable = '_migrations')
    {
        $this->linkName = $linkName;
        $this->logger = new \Psr\Log\NullLogger();
        $this->migrationsTable = $migrationsTable;
    }

    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    protected function loadMigrations()
    {
        if ($this->migrations === null) {
            $this->migrations = [];
            foreach (glob(PATH_MIGRATIONS . '*.php') as $migrationFile) {
                $this->migrations [] = require $migrationFile;
            }

            usort($this->migrations, function (Migration $a, Migration $b) {
                $this->migrationsSorter($a, $b);
            });
        }

        return $this->migrations;
    }

    protected function migrationsSorter(Migration $a, Migration $b)
    {
        return $a::ID <=> $b::ID;
    }

    protected function migrationsFilter(Migration $migration)
    {
        return $migration::LINK_NAME == $this->linkName;
    }

    protected function getMigrations()
    {
        return array_filter(
            static::loadMigrations(),
            function (Migration $migration) {
                return $this->migrationsFilter($migration);
            }
        );
    }

    public function getAppliedMigrationIds()
    {
        $this->checkMigrationsTable();
        return $this->getDb()->queryColumn(
            OSQL::select()->from($this->migrationsTable)->get('migration_id')
        );
    }

    public function run()
    {
        /** @var string[] $applied */
        $applied = $this->getAppliedMigrationIds();
        $this->logger->debug('Applied migrations: ' . implode(', ', $applied));
        /** @var Migration[] $notApplied */
        $notApplied = array_filter(
            $this->getMigrations(),
            function (Migration $m) use ($applied) {
                return !in_array($m::ID, $applied);
            }
        );

        usort($notApplied, function ($a, $b) { return $this->migrationsSorter($a, $b); });

        $this->logger->info('Database needs ' . count($notApplied) . ' migrations');

        foreach ($notApplied as $migration) {
            $this->logger->info('Applying migration ' . $migration::ID);
            $db = $this->getDb();
            try {
                $db->begin();

                $this->applyMigration($migration);
                $this->markApplied($migration::ID);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                throw $e;
            }
        }
    }
    
    protected function applyMigration(Migration $migration) 
    {
        $migration->up();
    }

    protected function getDb()
    {
        return DBPool::me()->getLink($this->linkName);
    }

    protected function markApplied($migrationId)
    {
        $this->getDb()->queryCount(
            OSQL::insert()->into($this->migrationsTable)
                ->arraySet([
                    'migration_id' => $migrationId,
                    'applied_at'   => Timestamp::makeNow(),
                ])
        );
    }

    protected function checkMigrationsTable()
    {
        try {
            $this->getDb()->getTableInfo($this->migrationsTable);
            return; // exists, ok
        } catch (ObjectNotFoundException $e) {

        }

        $this->logger->info('Creating migrations table: ' . $this->migrationsTable);
        $migrationsTable = DBTable::create($this->migrationsTable)
            ->addColumn(
                DBColumn::create(DataType::create(DataType::BIGINT)->setNull(false), 'id')
                    ->setPrimaryKey(true)
                    ->setAutoincrement(true)
            )
            ->addColumn(
                DBColumn::create(DataType::create(DataType::VARCHAR)->setSize(64)->setNull(false), 'migration_id')
            )
            ->addColumn(
                DBColumn::create(DataType::create(DataType::TIMESTAMP)->setNull(false), 'applied_at')
            );

        $this->getDb()->queryNull(OSQL::createTable($migrationsTable));
    }
}