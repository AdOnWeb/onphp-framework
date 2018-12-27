<?php

/**
 * @ingroup Builders
 **/
final class MigrationBuilder extends BaseBuilder
{
    public static function getBaseMigrationClass()
    {
        if (defined('ONPHP_MIGRATION_CLASS')) {
            return ONPHP_MIGRATION_CLASS;
        }
        return Migration::class;
    }

    public static function buildMigration($migrationId, $sourceName, array $up, array $down)
    {
        $dialect = DBPool::me()->getLink($sourceName)->getDialect();
        $baseClass = self::getBaseMigrationClass();

        $out = "<?php\n\n";
        $out .= "return new class extends $baseClass\n";
        $out .= "{\n";
        $out .= "    const LINK_NAME = '$sourceName';\n";
        $out .= "    const ID = '$migrationId';\n";
        $out .= "\n";

        $out .= "    public function up()\n";
        $out .= "    {\n";
        $out .= "        \$this->execute(\n";
        $out .= "            /** @lang {$dialect->getLanguageName()} */\n";
        $out .= "            <<<SQL\n";
        foreach ($up as $command) {
            $out .= $command . "\n";
        }
        $out .= "SQL\n";
        $out .= "        );\n";
        $out .= "    }\n";
        $out .= "\n";

        $out .= "    public function down()\n";
        $out .= "    {\n";
        $out .= "            \$this->execute(\n";
        $out .= "            /** @lang {$dialect->getLanguageName()} */\n";
        $out .= "            <<<SQL\n";
        foreach ($down as $command) {
            $out .= $command . "\n";
        }
        $out .= "SQL\n";
        $out .= "        );\n";
        $out .= "    }\n";

        $out .= "};\n";
        return $out;
    }

    public static function getDifferenceUp($schemes)
    {
        $diffs = [];
        foreach ($schemes as $sourceName => $schema) {
            $diff[$sourceName] = [];

            if (empty($schema->getTables())) {
                continue;
            }

            $db = DBPool::me()->getLink($sourceName);
            $dialect = $db->getDialect();

            $existed = [];
            foreach ($schema->getTableNames() as $tableName) {
                try {
                    $existed[$tableName] = $db->getTableInfo($tableName);
                } catch (ObjectNotFoundException $e) {
                    // skip
                }
            }

            // second run: create tables + check for cross-reference problem
            $created = [];
            foreach ($schema->getTableNames() as $tableName) {
                if (isset($existed[$tableName])) {
                    continue;
                }

                $newTable = clone $schema->getTableByName($tableName);
                foreach ($newTable->getColumns() as $column) {
                    if (!$column->hasReference()) {
                        continue;
                    }
                    $reference = $column->getReference();

                    /** @var DBTable|null $referencedTable */
                    if (isset($existed[$reference->getTable()->getName()])) {
                        $referencedTable = $existed[$reference->getTable()->getName()];
                    } else if (isset($created[$reference->getTable()->getName()])) {
                        $referencedTable = $created[$reference->getTable()->getName()];
                    } else {
                        $referencedTable = null;
                    }

                    if ($referencedTable && $referencedTable->getColumnByName($reference->getName())) {
                        continue;
                    }

                    $newTable->dropColumnByName($column->getName());
                }

                $created[$tableName] = $newTable;
                $diffs[$sourceName][] = $newTable->toDialectString($dialect);
            }

            // third run: create all alters
            foreach ($schema->getTables() as $table) {
                $target = $table;
                $source = $created[$table->getName()] ?? $existed[$table->getName()];
                if ($source) {
                    $alters = DBTable::findDifferences($db->getDialect(), $source, $target);
                    foreach ($alters as $alter) {
                        $diffs[$sourceName][] = $alter;
                    }
                } else {
                    $diffs[$sourceName][] = $target->toDialectString($db->getDialect());
                }
            }
        }
        return $diffs;
    }

    /**
     * @param $schemes
     * @return DBTable[]
     * @throws MissingElementException
     */
    public static function getDifferenceDown($schemes)
    {
        $diffs = [];
        foreach ($schemes as $sourceName => $schema) {
            $diff[$sourceName] = [];

            if (empty($schema->getTables())) {
                continue;
            }

            $db = DBPool::me()->getLink($sourceName);
            $dialect = $db->getDialect();

            $existed = [];
            foreach ($schema->getTables() as $declaredTable) {
                try {
                    $existingTable = $db->getTableInfo($declaredTable->getName());
                } catch (ObjectNotFoundException $e) {
                    $existingTable = null;
                }

                if ($existingTable) {
                    $alters = DBTable::findDifferences($db->getDialect(), $declaredTable, $existingTable);
                    foreach ($alters as $alter) {
                        $diffs[$sourceName][] = $alter;
                    }
                } else {
                    $diffs[$sourceName][] = OSQL::dropTable($declaredTable->getName())->toDialectString($dialect);
                }
            }
        }
        return $diffs;
    }
}

