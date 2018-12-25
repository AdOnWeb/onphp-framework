<?php

abstract class Migration
{
    const LINK_NAME = '';
    const ID        = '';

    abstract public function up();
    abstract public function down();

    protected function execute($sql)
    {
        $this->getDb()->queryRaw($sql);
    }

    protected function getDb()
    {
        return DBPool::me()->getLink(static::LINK_NAME);
    }
}