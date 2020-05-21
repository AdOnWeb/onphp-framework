<?php
/***************************************************************************
 *   Copyright (C) 2004-2009 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * PostgreSQL DB connector.
	 *
	 * @see http://www.postgresql.org/
	 *
	 * @ingroup DB
	**/
	class PgSQL extends DB
	{
	    /** @var null|int seconds  */
	    protected $connectTimeout = null;

		/**
		 * @return PostgresDialect
		**/
		public function getDialect()
		{
			return PostgresDialect::me();
		}

		/**
		 * @return PgSQL
		**/
		public function connect()
		{
			$conn =
				"host={$this->hostname} user={$this->username}"
				.($this->password ? " password={$this->password}" : null)
				.($this->basename ? " dbname={$this->basename}" : null)
				.($this->port ? " port={$this->port}" : null)
                .($this->connectTimeout ? " connect_timeout={$this->connectTimeout}" : null);

			if ($this->persistent)
				$this->link = pg_pconnect($conn);
			else
				$this->link = pg_connect($conn);

			if (!$this->link)
				throw new DatabaseException(
					'can not connect to PostgreSQL server: '.pg_errormessage()
				);

			if ($this->encoding)
				$this->setDbEncoding();

			pg_set_error_verbosity($this->link, PGSQL_ERRORS_VERBOSE);

			return $this;
		}

		/**
		 * @return PgSQL
		**/
		public function disconnect()
		{
			if ($this->isConnected())
				pg_close($this->link);

			return $this;
		}

		public function isConnected()
		{
			return is_resource($this->link);
		}

		/**
		 * misc
		**/

		public function obtainSequence($sequence)
		{
			$normalyzeSequence = mb_strtolower( trim( $sequence ) );
			if(
				'uuid' === $normalyzeSequence ||
				'uuid_id' === $normalyzeSequence
			) {
				return $this->obtainUuid();
			}

			$res = $this->queryRaw("select nextval('{$sequence}') as seq");
			$row = pg_fetch_assoc($res);
			pg_free_result($res);
			return $row['seq'];
		}

		/**
		 * @return string
		 */
		protected function obtainUuid()
		{
			return UuidUtils::generate();
		}

		/**
		 * @return PgSQL
		**/
		protected function setDbEncoding()
		{
			pg_set_client_encoding($this->link, $this->encoding);

			return $this;
		}

		/**
		 * query methods
		**/

		public function queryRaw($queryString)
		{
			$profiling = Profiling::create(array('db', get_class($this)), $queryString);
			try {
				$profiling->begin();
				$result = pg_query($this->link, $queryString);
				$profiling->end();
				return $result;

			} catch (BaseException $e) {
				$profiling->end();
				// manual parsing, since pg_send_query() and
				// pg_get_result() is too slow in our case
//				list($error, ) = explode("\n", pg_errormessage($this->link));
				$error = str_replace(array("\n", "\r"), array('   ', ''), trim(pg_errormessage($this->link)));
				sscanf($error, '%*s %[^:]', $code);

				if ($code == PostgresError::UNIQUE_VIOLATION) {
					$e = 'DuplicateObjectException';
					$code = null;
				} else
					$e = 'PostgresDatabaseException';

				throw new $e("[$this->hostname] " . $error.' - '.$queryString, $code);
			}
		}

		/**
		 * Same as query, but returns number of affected rows
		 * Returns number of affected rows in insert/update queries
		**/
		public function queryCount(Query $query)
		{
			return pg_affected_rows($this->queryNull($query));
		}

		public function queryRow(Query $query)
		{
			$res = $this->query($query);

			if ($this->checkSingle($res)) {
				$ret = pg_fetch_assoc($res);
				pg_free_result($res);
				return $ret;
			} else
				return null;
		}

		public function queryColumn(Query $query)
		{
			$res = $this->query($query);

			if ($res) {
				$array = array();

				while ($row = pg_fetch_row($res))
					$array[] = $row[0];

				pg_free_result($res);
				return $array;
			} else
				return null;
		}

		public function querySet(Query $query)
		{
			$res = $this->query($query);

			if ($res) {
				$array = array();

				while ($row = pg_fetch_assoc($res))
					$array[] = $row;

				pg_free_result($res);
				return $array;
			} else
				return null;
		}

		public function queryNumRows(Query $query) {
			$res = $this->query($query);

			if ($res) {
				return pg_num_rows($res);
			} else
				return null;
		}

		public function hasSequences()
		{
			return true;
		}

		/**
		 * @throws ObjectNotFoundException
		 * @return DBTable
		**/
		public function getTableInfo($tableName)
		{
			static $types = array(
                'time' => DataType::TIME,
                'date' => DataType::DATE,

                'timestamp'                   => DataType::TIMESTAMP,
                'timestamp without time zone' => DataType::TIMESTAMP,
                'timestamptz'                 => DataType::TIMESTAMPTZ,
                'timestamp with time zone'    => DataType::TIMESTAMPTZ,

                'boolean'  => DataType::BOOLEAN,

                'smallint' => DataType::SMALLINT,
                'int'      => DataType::INTEGER,
                'integer'  => DataType::INTEGER,
                'bigint'   => DataType::BIGINT,
                'numeric'  => DataType::NUMERIC,

                'float'            => DataType::REAL,
                'double precision' => DataType::DOUBLE,

                'character varying' => DataType::VARCHAR,
                'character'         => DataType::CHAR,
                'text'              => DataType::TEXT,

                'binary' => DataType::BINARY,

                'ip' => DataType::IP,

                'ip_range' => DataType::IP_RANGE,

                'cidr' => DataType::CIDR,

                'uuid'   => DataType::UUID,
                'hstore' => DataType::HSTORE,

                'json'  => DataType::JSON,
                'jsonb' => DataType::JSONB,

                'character varying[]' => DataType::SET_OF_STRINGS,
                'character[]'         => DataType::SET_OF_STRINGS,
                'smallint[]'          => DataType::SET_OF_INTEGERS,
                'integer[]'           => DataType::SET_OF_INTEGERS,
                'bigint[]'            => DataType::SET_OF_INTEGERS,
                'float[]'             => DataType::SET_OF_FLOATS,
                'double precision[]'  => DataType::SET_OF_FLOATS,

				// unhandled types, not ours anyway
				'tsvector'		=> null,

				'ltree'			=> null,
			);

            $columnsInfo = $this->querySet(
                OSQL::select()
                    ->from('pg_attribute')
                    ->get('attname', 'name')
                    ->get('attnotnull', 'not_null')
                    ->get('atthasdef', 'has_default')
                    ->get(SQLFunction::create('pg_catalog.format_type', 'atttypid', 'atttypmod'), 'type')
                    ->leftJoin(
                        'pg_class',
                        Expression::eq(
                            DBField::create('oid', 'pg_class'),
                            DBField::create('attrelid', 'pg_attribute')
                        )
                    )
                    ->leftJoin(
                        'pg_namespace',
                        Expression::eq(
                            DBField::create('oid', 'pg_namespace'),
                            DBField::create('relnamespace', 'pg_class')
                        )
                    )
                ->where(Expression::gt(DBField::create('attnum'), 0))
                ->andWhere(Expression::not(DBField::create('attisdropped'), 0))
                ->andWhere(Expression::eq(DBField::create('relname'), $tableName))
                ->andWhere(Expression::eq('nspname', 'public'))
                ->orderBy(DBField::create('attnum'))
            );

            if (!$columnsInfo) {
				throw new ObjectNotFoundException(
					"unknown table '{$tableName}'"
				);
			}

			$table = new DBTable($tableName);

			foreach ($columnsInfo as $info) {
                $name = $info['name'];
                $notNull = $info['not_null'] == 't';
                $hasDefault = $info['has_default'] == 't';
                $typeName = $info['type'];
                $size = null;
                $precision = null;
                $typeName = preg_replace_callback(
                    '/\((\d+)(?:,(\d+))?\)/',
                    function ($match) use (&$size, &$precision) {
                        $size = $match[1] ?? null;
                        $precision = $match[2] ?? null;
                        return '';
                    },
                    $typeName
                );

				Assert::isIndexExists(
				    $types, $typeName,
					'unknown type "' . $typeName .'" found in column "'. $tableName . '.' . $name . '"'
				);

                if ($types[$typeName] === null) {
                    continue;
                }

                try {
                    $type = DataType::create($types[$typeName])
                        ->setNull(!$notNull);

                    if ($type->hasSize() && $size) {
                        $type->setSize($size);
                    }
                    if ($type->hasPrecision() && $precision) {
                        $type->setPrecision($precision);
                    }
                } catch (\Throwable $e) {
                    throw new DatabaseException(
                        "failed to create DataType for '{$table->getName()}.$name' from info: \n"
                        . json_encode($info, JSON_PRETTY_PRINT),
                        0, $e
                    );
                }

				$column = new DBColumn($type, $name);

				$table->addColumn($column);
			}

			return $table;
		}

		private function checkSingle($result)
		{
			if (pg_num_rows($result) > 1)
				throw new TooManyRowsException(
					'query returned too many rows (we need only one)'
				);

			return $result;
		}

        /**
         * @return null|int
         */
        public function getConnectTimeout()
        {
            return $this->connectTimeout;
        }

        /**
         * @param int $connectTimeout
         * @return PgSQL
         */
        public function setConnectTimeout($connectTimeout)
        {
            $this->connectTimeout = $connectTimeout;
            return $this;
        }
    }
?>