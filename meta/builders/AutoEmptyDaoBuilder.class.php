<?php
/**
 * Билдер пустого DAO
 * @author Михаил Кулаковский <m@klkvsk.ru>
 * @date 2013.04.19
 */

/**
 * @ingroup Builders
 */
final class AutoEmptyDaoBuilder extends BaseDaoBuilder {

	public static function build(MetaClass $class)
	{
		if (
			is_null( $class->getParent() )
			||
			$class->getParent()->getPattern() instanceof InternalClassPattern
		) {
			$parentName = 'EmptyDAO';
		} else {
			$parentName = $class->getParent()->getName().'DAO';
		}

		$out = self::getHead();

		$out .= "abstract class Auto{$class->getName()}DAO extends {$parentName} \n{";

        $out .=
            self::buildPointers($class)
            ."\n}\n";

		return $out.self::getHeel();
	}

}
