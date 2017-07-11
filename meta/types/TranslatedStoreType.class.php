<?php
/***************************************************************************
 *   Copyright (C) 2013 by 2013 by Alexey Solomonov                        *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

/**
 * Данный тип будет хранить переводы в Hstore
 *
 * @ingroup Types
 **/
class TranslatedStoreType extends HstoreType {

    public function toColumnType() {
        return 'DataType::create(DataType::HSTORE)';
    }

    public function getClassName()
    {
        return 'TranslatedStore';
    }

    public function toGetter(
        MetaClass $class,
        MetaClassProperty $property,
        MetaClassProperty $holder = null
    ) {
        $name = $property->getName();

        $methodName = 'get'.ucfirst($property->getName());
        $classHint = $classHint = $property->getType()->getHint();

        return <<<EOT

{$classHint}
public function {$methodName}Store()
{
	if (!\$this->{$name}) {
		\$this->{$name} = new {$this->getClassName()}();
	}
	return \$this->{$name};
}

/**
 * @param string \$langCode
 * @return string|null
 **/
public function {$methodName}(\$langCode = null)
{
	/** @var \$store {$this->getClassName()} */
	\$store = \$this->{$methodName}Store();

	if (\$this->useTranslatedStore()) {
		return \$store;
	}

	\$languageCodes = array_merge(
		array(
			\$langCode ?: self::getLanguageCode(),
			self::getDefaultLanguageCode()
		),
		self::getLanguageCodes()
	);

	foreach (\$languageCodes as \$langCode) {
		if (\$store->has(\$langCode)) {
			return \$store->get(\$langCode);
		}
	}

	return null;
}

EOT;
    }

    public function toSetter(
        MetaClass $class,
        MetaClassProperty $property,
        MetaClassProperty $holder = null
    ) {
        $name = $property->getName();
        $methodNamePart = ucfirst($name);
        return <<<EOT

/**
 * @param string \${$name}
 * @param string \$langCode
 * @return \$this
**/
public function set{$methodNamePart}(\${$name}, \$langCode = null)
{
	if (\$this->useTranslatedStore()) {
		\$this->{$name} = \${$name};
		return \$this;
	}

	if (!\$langCode) {
		\$langCode = self::getLanguageCode();
	}

	\$store = \$this->get{$methodNamePart}Store();
	\$store->set(\$langCode, \${$name});

	return \$this;
}

EOT;
    }
} 