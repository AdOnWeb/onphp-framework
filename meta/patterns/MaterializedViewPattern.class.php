<?php

/**
 * @ingroup Patterns
 **/
final class MaterializedViewPattern extends BasePattern
{
    public function daoExists()
    {
        return true;
    }

    public function tableExists()
    {
        return false;
    }
}
