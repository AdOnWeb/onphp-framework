<?php
/***************************************************************************
 *   Copyright (C) 2007-2009 by Ivan Y. Khvostishkov                       *
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
	class PrimitiveForm extends BasePrimitive
	{
	    /** @var FormProvider */
		private $formProvider = null;

		private $composite = false;
		
		/**
		 * @throws WrongArgumentException
		 * @return PrimitiveForm
		 * 
		 * @deprecated You should use ofProto() instead
		**/
		public function of($className)
		{
			Assert::classExists($className);
			
			$protoClass = EntityProto::PROTO_CLASS_PREFIX.$className;
			
			Assert::classExists($protoClass);
			
			return $this->ofProto(Singleton::getInstance($protoClass));
		}

        /** @deprecated use setProvider */
        public function ofProto(EntityProto $proto)
		{
			return $this->setProvider($proto);
		}

		/** @deprecated use setProvider */
		public function ofAutoProto(AbstractProtoClass $proto)
		{
            return $this->setProvider($proto);
		}

        public function setProvider(FormProvider $formProvider)
        {
            $this->formProvider = $formProvider;

            return $this;
		}

        public function makeForm()
        {
            if (!$this->formProvider) {
                throw new WrongStateException(
                    "no form provider defined for PrimitiveFormsList '{$this->name}'"
                );
            }

            return $this->formProvider->makeForm();
		}
		
		/**
		 * @return PrimitiveForm
		 * 
		 * Either composition or aggregation, it is very important on import.
		**/
		public function setComposite($composite = true)
		{
			$this->composite = ($composite == true);
			
			return $this;
		}
		
		public function isComposite()
		{
			return $this->composite;
		}
		
		public function getClassName()
		{
		    if ($this->formProvider instanceof EntityProto) {
                return $this->formProvider->className();
            } else {
		        throw new WrongStateException('formProvider is not EntityProto');
            }
		}
		
		public function getProto()
		{
            if ($this->formProvider instanceof EntityProto) {
                return $this->formProvider;
            } else {
                throw new WrongStateException('formProvider is not EntityProto');
            }
		}

        public function getFormProvider()
        {
            return $this->formProvider;
		}
		
		/**
		 * @throws WrongArgumentException
		 * @return PrimitiveForm
		**/
		public function setValue($value)
		{
			Assert::isTrue($value instanceof Form);
			
			return parent::setValue($value);
		}
		
		/**
		 * @throws WrongArgumentException
		 * @return PrimitiveForm
		**/
		public function importValue($value)
		{
			if ($value !== null)
				Assert::isTrue($value instanceof Form);
			
			if (!$this->value || !$this->composite) {
				$this->value = $value;
			} else {
				throw new WrongStateException(
					'composite objects should not be broken'
				);
			}
			
			return ($value->getErrors() ? false : true);
		}
		
		public function exportValue()
		{
			if (!$this->value)
				return null;
			
			return $this->value->export();
		}
		
		public function getInnerErrors()
		{
			if ($this->value)
				return $this->value->getInnerErrors();
			
			return array();
		}
		
		public function import($scope)
		{
			return $this->actualImport($scope, true);
		}
		
		public function unfilteredImport($scope)
		{
			return $this->actualImport($scope, false);
		}
		
		private function actualImport($scope, $importFiltering)
		{
			if (!isset($scope[$this->name]))
				return null;
			
			$this->raw = $scope[$this->name];
			
			if (!$this->value || !$this->composite)
				$this->value = $this->makeForm();
			
			if (!$importFiltering) {
				$this->value->
					disableImportFiltering()->
					import($this->raw)->
					enableImportFiltering();
			} else {
				$this->value->import($this->raw);
			}
			
			$this->imported = true;
			
			if ($this->value->getErrors())
				return false;
			
			return true;
		}
	}
?>