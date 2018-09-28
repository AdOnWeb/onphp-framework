<?php
/****************************************************************************
 *   Copyright (C) 2004-2007 by Konstantin V. Arkhipov, Anton E. Lebedevich *
 *                                                                          *
 *   This program is free software; you can redistribute it and/or modify   *
 *   it under the terms of the GNU Lesser General Public License as         *
 *   published by the Free Software Foundation; either version 3 of the     *
 *   License, or (at your option) any later version.                        *
 *                                                                          *
 ****************************************************************************/

	/**
	 * Wrapper around given childs of LogicalObject with custom logic-glue's.
	 * 
	 * @ingroup Logic
	**/
	final class LogicalChain extends SQLChain
	{
		/**
		 * @return LogicalChain
		**/
		public static function block($args, $logic)
		{
			Assert::isTrue(
				($logic == BinaryExpression::EXPRESSION_AND)
				|| ($logic == BinaryExpression::EXPRESSION_OR),
				
				"unknown logic '{$logic}'"
			);
			
			$logicalChain = new self;
			
			foreach ($args as $arg) {
				if (
					!$arg instanceof LogicalObject
					&& !$arg instanceof SelectQuery
				)
					throw new WrongArgumentException(
						'unsupported object type: '.get_class($arg)
					);
				
				$logicalChain->exp($arg, $logic);
			}
			
			return $logicalChain;
		}
		
		/**
		 * @return LogicalChain
		**/
		public function expAnd(LogicalObject $exp)
		{
			return $this->exp($exp, BinaryExpression::EXPRESSION_AND);
		}
		
		/**
		 * @return LogicalChain
		**/
		public function expOr(LogicalObject $exp)
		{
			return $this->exp($exp, BinaryExpression::EXPRESSION_OR);
		}
		
		public function toBoolean(LogicalOperandProvider $operandProvider)
		{
		    $this->regroupByPrecedence();

            /** @var LogicalObject[] $chain */
			$chain = &$this->chain;
			
			$size = count($chain);
			
			if ($size == 0) {
                throw new WrongArgumentException(
                    'empty chain can not be calculated'
                );
			}

			if ($size == 1) {
			    // single item -- quick result
                return $chain[0]->toBoolean($operandProvider);
			}

            // for regrouped chain, all elements logic is the same
            $logic = $this->logic[0];

            if ($logic == BinaryExpression::EXPRESSION_OR) {
                // "false" until first "true"
                $initial = false;
                $breakAt = true;
            } else {
                // "true" until first "false"
                $initial = true;
                $breakAt = false;
            }

            $result = $initial;
            foreach ($chain as $logicalObject) {
                $result = $logicalObject->toBoolean($operandProvider);
                if ($result == $breakAt) {
                    break;
                }
            }

            return $result;
		}

        /**
         * For a chain with mixed AND/OR logic, this would create new chain (OR-block) with existing AND-logic parts
         * grouped into separate AND-blocks
         *
         *   a OR b AND b  ->  a OR (b AND c)
         *
         * @return LogicalChain
         */
        public function regroupByPrecedence()
        {
            // root is the OR block of items, some of which can be AND blocks
            $root = Expression::orBlock();
            // temporary block for collecting AND logic
            $andBlock = Expression::andBlock();

            for ($i = 0; $i < count($this->chain); $i++) {
                $value = $this->chain[$i];
                $logic = $this->logic[$i];

                switch ($logic) {
                    case BinaryExpression::EXPRESSION_AND:
                        // add to temporary AND block
                        $andBlock->expAnd($value);
                        break;

                    case BinaryExpression::EXPRESSION_OR:
                        if ($andBlock->getSize() != 0) {
                            // push finished AND block to global OR block
                            $root->expOr($andBlock);
                            // reset temporary AND block
                            $andBlock = Expression::andBlock();
                        }
                        // add OR logic to OR block
                        $root->expOr($value);
                        break;

                    default:
                        throw new UnexpectedValueException($logic);
                }
            }

            if ($andBlock->getSize() != 0) {
                // something left
                if ($root->getSize() == 0) {
                    // all items was in AND logic
                    $root = $andBlock;
                } else {
                    // push last AND block to global OR block
                    $root->expOr($andBlock);
                }
            }

            $this->logic = $root->logic;
            $this->chain = $root->chain;

            return $this;
		}
	}
?>