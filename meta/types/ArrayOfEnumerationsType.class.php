<?php
/**
 *
 * @author Михаил Кулаковский <m@klkvsk.ru>
 * @date 14.01.2015
 */

class ArrayOfEnumerationsType extends ArrayOfIntegersType {

	protected $className;

	function __construct($type, array $parameters) {
		Assert::isNotEmptyArray($parameters, 'enumeration class name is not provided');
		list($enumerationClassName) = $parameters;
        $this->className = $enumerationClassName;
	}

    public function getClass()
    {
        $class = MetaConfiguration::me()->getCorePlugin()->getClassByName($this->getClassName());

        Assert::isTrue(
            $class->getPattern() instanceof EnumerationClassPattern,
            'only enumeration classes can be provided for ArrayOfEnumerations type'
        );

        return $class;
	}

    public function getClassName()
    {
        return $this->className;
	}

	public function toGetter(
		MetaClass $class,
		MetaClassProperty $property,
		MetaClassProperty $holder = null
	)
	{
        if ($holder)
            $name = $holder->getName().'->get'.ucfirst($property->getName()).'()';
        else
            $name = $property->getName();

        $methodName = 'get'.ucfirst($property->getName());

        $code = <<<EOT

/**
 * @return {$this->getHint()}
 */
public function {$methodName}()
{
	return \$this->{$name};
}

EOT;

		$code .= $this->toListGetter($class, $property, $holder);

		return $code;
	}

	public function toListGetter(
		MetaClass $class,
		MetaClassProperty $property,
		MetaClassProperty $holder = null
	)
	{
		if ($holder)
			$name = $holder->getName().'->get'.ucfirst($property->getName()).'()';
		else
			$name = $property->getName();

		$methodName = 'get'.ucfirst($property->getName()).'List';

		return <<<EOT

/**
 * @return {$this->getClass()->getName()}[]
 */
public function {$methodName}()
{
	return {$this->getClass()->getName()}::createList(\$this->{$name});
}

EOT;
	}

    public function toSetter(
        MetaClass $class,
        MetaClassProperty $property,
        MetaClassProperty $holder = null
    )
    {
        $name = $property->getName();
        $methodName = 'set'.ucfirst($name);

        $nullArg = $property->isRequired() ? '' : ' = null';
        $nullParam = $property->isRequired() ? '' : '|null';

        if ($holder) {
            Assert::isUnreachable();
        } else {
            $code = <<<EOT

/**
 * @param {$this->getHint()}{$nullParam} \${$name}
 * @return \$this
 */
public function {$methodName}(array \${$name}{$nullArg})
{
    \${$name} = array_map(
        function (\$value) {
            switch (true) {
                case \$value instanceof {$this->getClass()->getName()}: return \$value->getId();
                case Assert::checkInteger(\$value): return intval(\$value);
                case is_scalar(\$value): return \$value;
                default: throw new WrongArgumentException(Assert::dumpArgument(\$value));
            }
        }, 
        \${$name}
    );
	\$this->{$name} = \${$name};

	return \$this;
}

EOT;
            $code .= $this->toListSetter($class, $property, $holder);

            return $code;
        }

        Assert::isUnreachable();
    }

    public function toListSetter(
		MetaClass $class,
		MetaClassProperty $property,
		MetaClassProperty $holder = null
	)
	{
		$name = $property->getName();
		$methodName = 'set'.ucfirst($name).'List';

		$default = $property->isRequired() ? '' : ' = null';

		if ($holder) {
			Assert::isUnreachable();
		} else {
			return <<<EOT

/**
 * @param \${$name} {$this->getClass()->getName()}[]
 * @return \$this
 */
public function {$methodName}(array \${$name}{$default})
{
    \${$name} = array_map(
        function (\$value) {
            if (\$value instanceof {$this->getClass()->getName()}) {
                return \$value->getId();
            } 
            throw new WrongArgumentException(Assert::dumpArgument(\$value));
        }, 
        \${$name}
    );
	\$this->{$name} = \${$name};

	return \$this;
}

EOT;
		}

		Assert::isUnreachable();
	}

}