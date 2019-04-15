<?php
/***************************************************************************
 *   Copyright (C) 2005-2008 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * @ingroup DAOs
	**/
	abstract class StorableDAO extends ProtoDAO
	{
		public function take(Identifiable $object)
		{
			return
				$object->getId()
					? $this->merge($object, true)
					: $this->add($object);
		}
		
		public function add(Identifiable $object)
		{
			//Support non-"id" identifier columns
			if (method_exists($this, 'getIdName')) {
				$method = 'set'.ucfirst($this->getIdName());
			} else {
				$method = 'setId';
			}
			return
				$this->inject(
					OSQL::insert(),
					$object->{$method}(
						DBPool::getByDao($this)->obtainSequence(
							$this->getSequence()
						)
					)
				);
		}
		
		public function save(Identifiable $object)
		{
			return
				$this->inject(
					$this->targetizeUpdateQuery(OSQL::update(), $object),
					$object
				);
		}
		
		public function import(Identifiable $object)
		{
			return
				$this->inject(
					OSQL::insert(),
					$object
				);
		}
		
		public function merge(Identifiable $object, $cacheOnly = true)
		{
			Assert::isNotNull($object->getId());
			
			$this->checkObjectType($object);
			
			$old = Cache::worker($this)->getCachedById($object->getId());
			
			if (!$old) { // unlikely
				if ($cacheOnly)
					return $this->save($object);
				else
					$old = Cache::worker($this)->getById($object->getId());
			}
			
			return $this->unite($object, $old);
		}

        /**
         * @param Identifiable|Prototyped $object
         * @param Identifiable $old
         * @return Identifiable
         */
		public function unite(
			Identifiable $object, Identifiable $old
		)
		{
			Assert::isNotNull($object->getId());
			
			Assert::isTypelessEqual(
				$object->getId(), $old->getId(),
				'cannot merge different objects'
			);

            $this->runTrigger($object, 'onBeforeSave');

            $query = OSQL::update($this->getTable());
			
			foreach ($this->getProtoClass()->getPropertyList() as $property) {
				$getter = $property->getGetter();
				
				if ($property->getClassName() === null) {
					$changed = ($old->$getter() !== $object->$getter());
				} else {
					/**
					 * way to skip pointless update and hack for recursive
					 * comparsion.
					**/
					$changed =
						($old->$getter() !== $object->$getter())
						|| ($old->$getter() != $object->$getter());
				}
				
				if ($changed)
					$property->fillQuery($query, $object);
			}
			
			if (!$query->getFieldsCount())
				return $object;
			
			$this->targetizeUpdateQuery($query, $object);
			
			return $this->doInject($query, $object);
		}

        /**
         * @param Identifiable|Prototyped $object
         * @param array $propertyNames
         * @return Identifiable
         */
		public function savePartial(
			Identifiable $object, array $propertyNames
		)
		{
			Assert::isNotNull($object->getId());

			if (empty($propertyNames)) {
			    return $object;
            }

            $this->runTrigger($object, 'onBeforeSave');

            $query = OSQL::update($this->getTable());

			foreach ($propertyNames as $propertyName) {
			    $property = $this->getProtoClass()->getPropertyByName($propertyName);
                $property->fillQuery($query, $object);
			}

			$this->targetizeUpdateQuery($query, $object);

			return $this->doInject($query, $object);
		}
		
		/**
		 * @return UpdateQuery
		**/
		protected function targetizeUpdateQuery(
			UpdateQuery $query,
			Identifiable $object
		)
		{
			return $query->where(Expression::eqId($this->getIdName(), $object));
		}
	}
?>