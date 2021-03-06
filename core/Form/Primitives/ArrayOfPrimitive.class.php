<?php
/**
 * @author Mikhail Kulakovskiy <m@klkvsk.ru>
 * @date 2015-11-27
 */

class ArrayOfPrimitive extends FiltrablePrimitive {
    /** @var BasePrimitive */
    protected $primitive;
    /** @var bool */
    protected $keepKeys = false;

    public function setPrimitive(BasePrimitive $primitive)
    {
        $this->primitive = $primitive;
        $this->setRequired($primitive->isRequired());
        return $this;
    }

    public function getPrimitive()
    {
        return $this->primitive;
    }

    public function setKeepKeys($bool)
    {
        $this->keepKeys = $bool;
        return $this;
    }

    public function isKeepKeys()
    {
        return $this->keepKeys;
    }

    public function import($scope)
    {
        if (!BasePrimitive::import($scope))
            return null;

        $this->imported = true;
        $this->customError = null;
        $this->value = [];

        foreach ($this->raw as $key => $element) {
            $this->primitive->clean();
            if (!$this->primitive->import([ $this->getName() => $element ])) {
                $this->imported = false;
            }
            $this->customError = $this->customError ?: $this->primitive->getCustomError();
            $value = $this->primitive->value;
            if ($this->isKeepKeys()) {
                $this->value[$key] = $value;
            } else {
                $this->value []= $value;
            }
        }

        if (!$this->imported) {
            return false;
        }

        $this->selfFilter();

        if (
            is_array($this->value)
            && !($this->min && count($this->value) < $this->min)
            && !($this->max && count($this->value) > $this->max)
        ) {
            return true;
        } else {
            $this->value = null;
        }

        return false;
    }

}