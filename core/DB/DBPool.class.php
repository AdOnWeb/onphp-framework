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
	 * Pool of DB's instances.
	 *
	 * @ingroup DB
	**/
	final class DBPool extends Singleton implements Instantiatable
	{
	    /** @var DBInterface */
		private $default = null;

		/** @var DBInterface[] */
		private $pool = array();

		/**
		 * @return DBPool
		**/
		public static function me()
		{
			return Singleton::getInstance(__CLASS__);
		}

        /**
         * @param GenericDAO $dao
         * @return DBInterface
         */
		public static function getByDao(GenericDAO $dao)
		{
			return self::me()->getLink($dao->getLinkName());
		}

        /**
         * @param DBInterface $db
         * @return DBPool
         */
		public function setDefault(DBInterface $db)
		{
			$this->default = $db;

			return $this;
		}

		/**
		 * @return DBPool
		**/
		public function dropDefault()
		{
			$this->default = null;

			return $this;
		}

        /**
         * @param $name
         * @param DBInterface $db
         * @return DBPool
         * @throws WrongArgumentException
         */
		public function addLink($name, DBInterface $db)
		{
			if (isset($this->pool[$name]))
				throw new WrongArgumentException(
					"already have '{$name}' link"
				);

			$this->pool[$name] = $db;

			return $this;
		}

        /**
         * @param $name
         * @return DBPool
         * @throws MissingElementException
         */
		public function dropLink($name)
		{
			if (!isset($this->pool[$name]))
				throw new MissingElementException(
					"link '{$name}' not found"
				);

			unset($this->pool[$name]);

			return $this;
		}

        /**
         * @param null $name
         * @param bool $connect
         * @return DBInterface
         * @throws MissingElementException
         */
		public function getLink($name = null, $connect = true)
		{
			$link = null;

			// single-DB project
			if (!$name) {
				if (!$this->default)
					throw new MissingElementException(
						'i have no default link and requested link name is null'
					);

				$link = $this->default;
			} elseif (isset($this->pool[$name]))
				$link = $this->pool[$name];

			if ($link) {
				if ($connect && !$link->isConnected())
					$link->connect();

				return $link;
			}

			throw new MissingElementException(
				"can't find link with '{$name}' name"
			);
		}

        public function getLinkNames()
        {
            return array_keys($this->pool);
		}

		/**
		 * @return DBPool
		**/
		public function shutdown()
		{
			$this->disconnect();

			$this->default = null;
			$this->pool = array();

			return $this;
		}

		/**
		 * @return DBPool
		**/
		public function disconnect()
		{
			if ($this->default)
				$this->default->disconnect();

			foreach ($this->pool as $db)
				$db->disconnect();

			return $this;
		}
	}
