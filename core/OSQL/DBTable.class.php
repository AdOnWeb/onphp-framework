<?php
/***************************************************************************
 *   Copyright (C) 2006-2007 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * @ingroup OSQL
	**/
	final class DBTable implements DialectString
	{
	    /** @var string */
		private $name		= null;
		/** @var DBColumn[] */
		private $columns	= array();

		private $uniques	= array();
		
		/**
		 * @return DBTable
		**/
		public static function create($name)
		{
			return new self($name);
		}
		
		public function __construct($name)
		{
			$this->name = $name;
		}

        /**
         * @return DBColumn[]
         */
		public function getColumns()
		{
			return $this->columns;
		}
		
		/**
		 * @return DBTable
		**/
		public function addUniques(/* ... */)
		{
			Assert::isTrue(func_num_args() > 0);
			
			$uniques = array();
			
			foreach (func_get_args() as $name) {
				// check existence
				$this->getColumnByName($name);
				
				$uniques[] = $name;
			}
			
			$this->uniques[] = $uniques;
			
			return $this;
		}
		
		public function getUniques()
		{
			return $this->uniques;
		}
		
		/**
		 * @throws WrongArgumentException
		 * @return DBTable
		**/
		public function addColumn(DBColumn $column)
		{
			$name = $column->getName();
			
			Assert::isFalse(
				isset($this->columns[$name]),
				"column '{$name}' already exist"
			);
			
			$this->columns[$name] = $column;
			
			$column->setTable($this);
			
			return $this;
		}
		
		/**
		 * @throws MissingElementException
		 * @return DBColumn
		**/
		public function getColumnByName($name)
		{
			if (!isset($this->columns[$name]))
				throw new MissingElementException(
					"column '{$name}' does not exist"
				);
			
			return $this->columns[$name];
		}
		
		/**
		 * @return DBTable
		**/
		public function dropColumnByName($name)
		{
			if (!isset($this->columns[$name])) {
                throw new MissingElementException(
                    "column '{$name}' does not exist"
                );
            }

			if (in_array($this->columns[$name], $this->uniques)) {
                throw new WrongArgumentException(
                    "column '{$name}' is used in uniques, can not be dropped"
                );
            }

			unset($this->columns[$name]);

			return $this;
		}
		
		/**
		 * @return DBTable
		**/
		public function setName($name)
		{
			$this->name = $name;
			
			return $this;
		}
		
		public function getName()
		{
			return $this->name;
		}

		public function toDialectString(Dialect $dialect)
		{
			return OSQL::createTable($this)->toDialectString($dialect);
		}

		// TODO: consider port to AlterTable class (unimplemented yet)
		public static function findDifferences(
			Dialect $dialect,
			DBTable $source,
			DBTable $target
		)
		{
			$out = array();
			
			$head = 'ALTER TABLE '.$dialect->quoteTable($target->getName());

			/** @var DBColumn[] $sourceColumns */
			$sourceColumns = $source->getColumns();
			/** @var DBColumn[] $targetColumns */
			$targetColumns = $target->getColumns();

			foreach ($sourceColumns as $name => $column) {
				if (isset($targetColumns[$name])) {
					if (
						($column->getType()->getId() != $targetColumns[$name]->getType()->getId()
							|| ($column->getType()->hasSize() && $column->getType()->getSize() != $targetColumns[$name]->getType()->getSize())
							|| ($column->getType()->hasPrecision() && $column->getType()->getPrecision() != $targetColumns[$name]->getType()->getPrecision())
						)
						// for vertica: bigint == integer
						&& !($dialect instanceof VerticaDialect && (
							($targetColumns[$name]->getType()->getId() == DataType::INTEGER
								&& $column->getType()->getId() == DataType::BIGINT) ||
							($targetColumns[$name]->getType()->getId() == DataType::BIGINT
								&& $column->getType()->getId() == DataType::INTEGER)
						))
					) {
						$targetColumn = $targetColumns[$name];

						$out[] =
							$head
							.' ALTER COLUMN '.$dialect->quoteField($name)
							.' TYPE '.$targetColumn->getType()->toTypeDefinition($dialect)
							. ($targetColumn->getType()->in([ DataType::JSON, DataType::JSONB ])
								? ' USING NULL'
								: '')
							. "; \t\t -- (has " . $column->getType()->toTypeDefinition($dialect) . ')';
					}
					
					if (
						$column->getType()->isNull()
						!= $targetColumns[$name]->getType()->isNull()
					) {
						$out[] =
							$head
							.' ALTER COLUMN '.$dialect->quoteField($name)
							.' '
							.(
								$targetColumns[$name]->getType()->isNull()
									? 'DROP'
									: 'SET'
							)
							.' NOT NULL;';
					}
				} else {
					$out[] =
						$head
						.' DROP COLUMN '.$dialect->quoteField($name).';';
				}
			}
			
			foreach ($targetColumns as $name => $column) {
				if (!isset($sourceColumns[$name])) {
					$out[] =
						$head
						.' ADD COLUMN '
						.$column->toDialectString($dialect).';';
					
					if ($column->hasReference()) {
						$out[] =
							'CREATE INDEX '.$dialect->quoteField($target->getName().'_'.$name.'_idx')
							.' ON '.$dialect->quoteTable($target->getName()).
							'('.$dialect->quoteField($name).');';
					}
				}
			}
			
			return $out;
		}
	}
?>