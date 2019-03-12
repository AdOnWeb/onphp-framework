<?php


class MongoCustomId implements JsonSerializable, Serializable
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getValue()
    {
        return $this->id;
    }

    public function __toString()
    {
        return (string)$this->id;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return $this->getValue();
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return $this->getValue();
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $this->id = $serialized;
    }


}
