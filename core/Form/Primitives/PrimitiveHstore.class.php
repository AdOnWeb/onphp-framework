<?php
/****************************************************************************
 *   Copyright (C) 2009 by Sergey S. Sergeev                                *
 *                                                                          *
 *   This program is free software; you can redistribute it and/or modify   *
 *   it under the terms of the GNU Lesser General Public License as         *
 *   published by the Free Software Foundation; either version 3 of the     *
 *   License, or (at your option) any later version.                        *
 *                                                                          *
 ****************************************************************************/

	/**
	 * @ingroup Primitives
	**/
	class PrimitiveHstore extends BasePrimitive
	{
		protected $formMapping	= array();
		
		/**
		 * @return PrimitiveHstore
		**/
		public function setFormMapping($array)
		{
			$this->formMapping = $array;

			return $this;
		}
		
		public function getFormMapping()
		{
			return $this->formMapping;
		}
		
		public function getInnerErrors()
		{
			if ($this->value instanceof Form)
				return $this->value->getInnerErrors();
			
			return array();
		}
		
		/**
		 * @return Form
		**/
		public function getInnerForm()
		{
			return $this->value;
		}
		
		public function getValue()
		{
			return $this->exportValue();
		}

        public function getSafeValue()
        {
            return $this->getValue() ?: $this->getDefault();
		}

        public function getRawValue()
        {
            if (is_array($this->raw)) {
                return Hstore::make($this->raw);
            } else if ($this->raw === null) {
                return null;
            } else {
                return Hstore::make([]);
            }
		}

        public function getActualValue()
        {
            $value  = $this->getValue();
            $raw    = $this->getRawValue();

            if (null !== $value)
                return $value;
            elseif ($this->imported)
                return $raw;

            return $this->getDefault();
		}

		/**
		 * @throws WrongArgumentException
		 * @return boolean
		**/
		public function importValue($value)
		{
			if ($value === null)
				return parent::importValue(null);
			
			Assert::isTrue($value instanceof Hstore, 'importValue');
				
			if (!$this->value instanceof Form)
				$this->value = $this->makeForm();
			
			$this->value->import($value->getList());
			$this->imported = true;
			
			return
				$this->value->getErrors()
					? false
					: true;
		}
		
		public function import($scope)
		{
			if (!isset($scope[$this->name]))
				return null;
			
			$this->raw = $scope[$this->name];
			
			if (!$this->value instanceof Form)
				$this->value = $this->makeForm();
			
			$this->value->import($this->raw);
			$this->imported = true;
			
			if ($this->value->getErrors())
				return false;
			
			return true;
		}
		
		/**
		 * @return Hstore
		**/
		public function exportValue()
		{
			if (!$this->value instanceof Form)
				return null;
			
			return Hstore::make($this->value->export());
		}
		
		/**
		 * @return Form
		**/
		protected function makeForm()
		{
			$form = Form::create();

			if ($this->getFormMapping()) {
                foreach ($this->getFormMapping() as $primitive) {
                    $form->add($primitive);
                }
            } else {
			    // allow to fill form with all fields provided
                $raw = $this->getRawValue();
                if ($raw instanceof Hstore) {
                    foreach ($raw->getList() as $key => $value) {
                        $form->add(Primitive::anyType($key));
                    }
                }
            }

			return $form;
		}
	}
?>