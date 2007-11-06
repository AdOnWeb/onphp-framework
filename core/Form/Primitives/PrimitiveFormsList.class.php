<?php
/***************************************************************************
 *   Copyright (C) 2007 by Ivan Y. Khvostishkov                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup Primitives
	**/
	final class PrimitiveFormsList extends PrimitiveForm
	{
		protected $value = array();
		
		public function import($scope)
		{
			if (!$this->className)
				throw new WrongStateException(
					"no class defined for PrimitiveFormsList '{$this->name}'"
				);
			
			if (!isset($scope[$this->name]))
				return null;
			
			$this->rawValue = $scope[$this->name];
			
			$this->imported = true;
				
			if (!is_array($scope[$this->name]))
				return false;
			
			$error = false;
			
			$this->value = array();
			
			foreach ($scope[$this->name] as $id => $value) {
				$this->value[$id] =
					$this->proto->makeForm()->
						import($value);
				
				if ($this->value[$id]->getErrors())
					$error = true;
			}
			
			if ($error)
				return false;
			
			return true;
		}
		
		public function importValue($value)
		{
			if ($value !== null)
				Assert::isArray($value);
					
			return $this->import(
				array($this->name => $value)
			);
		}
		
		public function exportValue()
		{
			if (!$this->isImported())
				return null;
			
			$result = array();
			
			foreach ($this->value as $id => $form) {
				$result[$id] = $form->export();
			}
			
			return $result;
		}
	}
?>