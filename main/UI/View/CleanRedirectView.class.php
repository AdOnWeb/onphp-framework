<?php
/***************************************************************************
 *   Copyright (C) 2009 by Ivan Y. Khvostishkov                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

/**
 * @ingroup Flow
 **/
class CleanRedirectView implements View
{
    protected $url = null;
    protected $status = null;

    public function __construct($url)
    {
        $this->url = $url;
        $this->status = HttpStatus::create(HttpStatus::CODE_302);
    }

    /**
     * @return CleanRedirectView
     **/
    public static function create($url)
    {
        return new self($url);
    }

    public function render($model = null)
    {
        HeaderUtils::sendHttpStatus($this->status);
        HeaderUtils::redirectRaw($this->getLocationUrl($model));
    }

    protected function getLocationUrl($model = null)
    {
        return $this->getUrl();
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getHttpStatus()
    {
        return $this->status;
    }

    public function setHttpStatus(HttpStatus $status)
    {
        $this->status = $status;

        return $this;
    }
}
