<?php
/***************************************************************************
 *   Copyright (C) 2007-2008 by Ivan Y. Khvostishkov                       *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * @ingroup Primitives
	**/
	final class PrimitiveEnumerationList extends PrimitiveEnumeration
	{
		protected $value = array();

        /**
         * due to historical reasons, by default we're dealing only with
         * integer identifiers, this problem correctly fixed in master branch
         */
        protected $scalar = false;

        /**
         * @return IdentifiablePrimitive
         **/
        public function setScalar($orly = false)
        {
            $this->scalar = ($orly === true);

            return $this;
        }

        /**
		 * @return PrimitiveEnumerationList
		**/
		public function clean()
		{
			parent::clean();
			
			// restoring our very own default
			$this->value = array();
			
			return $this;
		}
		
		/**
		 * @return PrimitiveEnumerationList
		**/
		public function setValue(/* Enumeration */ $value)
		{
			if ($value) {
				Assert::isArray($value);
				Assert::isInstance(current($value), 'Enumeration');
			}
			
			$this->value = $value;
			
			return $this;
		}
		
		public function importValue($value)
		{
			if (is_array($value)) {
				try {
				    foreach ($value as $id) {
                        if (!$this->checkId($id)) {
                            throw new WrongArgumentException($id);
                        }
                    }

					return $this->import(
						array($this->name => $value)
					);
				} catch (WrongArgumentException $e) {
					return $this->import(
						array($this->name => ArrayUtils::getIdsArray($value))
					);
				}
			}
			
			return parent::importValue($value);
		}
		
		public function import($scope)
		{
			if (!$this->className)
				throw new WrongStateException(
					"no class defined for PrimitiveIdentifierList '{$this->name}'"
				);
			
			if (!BasePrimitive::import($scope))
				return null;
			
			if (!is_array($scope[$this->name]))
				return false;
			
			$list = array_unique($scope[$this->name]);
			
			$values = array();
			
			foreach ($list as $id) {
				if (!$this->checkId($id))
					return false;
				
				$values[] = $id;
			}
			
			$objectList = array();
			
			foreach ($values as $value) {
				$className = $this->className;
				try {
                    $objectList[] = new $className($value);
                } catch (MissingElementException $e) {
				    return false;
                }
			}
			
			if (count($objectList) == count($values)) {
				$this->value = $objectList;
				return true;
			}
			
			return false;
		}
		
		public function exportValue()
		{
			if (!$this->value)
				return null;
			
			return ArrayUtils::getIdsArray($this->value);
		}


        protected function checkId($number)
        {
            if ($this->scalar)
                return Assert::checkScalar($number);
            else
                return Assert::checkInteger($number);
        }

	}
?>