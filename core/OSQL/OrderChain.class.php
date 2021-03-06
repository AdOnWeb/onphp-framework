<?php
/***************************************************************************
 *   Copyright (C) 2007 by Konstantin V. Arkhipov                          *
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
	final class OrderChain implements DialectString, MappableObject
	{
		private $chain = array();
		
		/**
		 * @return OrderChain
		**/
		public static function create()
		{
			return new self;
		}
		
		/**
		 * @return OrderChain
		**/
		public function add($order)
		{
			$this->chain[] = $this->makeOrder($order);
			
			return $this;
		}
		
		/**
		 * @return OrderChain
		**/
		public function prepend($order)
		{
			if ($this->chain)
				array_unshift($this->chain, $this->makeOrder($order));
			else
				$this->chain[] = $this->makeOrder($order);
			
			return $this;
		}
		
		/**
		 * @return OrderBy
		**/
		public function getLast()
		{
			return end($this->chain);
		}

		/**
		 * @return OrderBy
		**/
		public function getFirst()
		{
			return reset($this->chain);
		}

        /**
         * @return OrderBy[]
         */
		public function getList()
		{
			return $this->chain;
		}

		public function dropChain()
		{
			$this->chain = array();
		}
		
		public function getCount()
		{
			return count($this->chain);
		}
		
		/**
		 * @return OrderChain
		**/
		public function toMapped(ProtoDAO $dao, JoinCapableQuery $query)
		{
			$chain = new self;

			foreach ($this->chain as $order) {
                /**
                 * если используется сортировка по ключ hstore,
                 * то необходимо добавить такое же выражение в список выбираемых столбцов,
                 * иначе получим ошибку от postgres
                 */
                if (
                    $query->isDistinct()
                    && $dao->isTranslatedField($order->getField())
                ) {
                    $query->get(DBHstoreField::create(
                        $dao->getProtoClass()->getPropertyByName($order->getField())->getColumnName(),
                        $dao->getTable(),
                        $dao->getLanguageCode()
                    ));
                }
                $chain->add($order->toMapped($dao, $query));
            }

			return $chain;
		}
		
		public function toDialectString(Dialect $dialect)
		{
			if (!$this->chain)
				return null;
			
			$out = null;
			
			foreach ($this->chain as $order)
				$out .= $order->toDialectString($dialect).', ';
			
			return rtrim($out, ', ');
		}
		
		/**
		 * @return OrderBy
		**/
		private function makeOrder($object)
		{
			if ($object instanceof OrderBy)
				return $object;
			elseif ($object instanceof DialectString)
				return new OrderBy($object);
			
			return
				new OrderBy(
					new DBField($object)
				);
		}
	}
?>