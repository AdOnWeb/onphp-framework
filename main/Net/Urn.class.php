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

	/**
	 * URN is an absolute URI without authority part.
	 * 
	 * @ingroup Net
	**/
	final class Urn extends GenericUri
	{
		protected $schemeSpecificPart	= null;
		
		protected static $knownSubSchemes	= array(
			'urn'		=> Urn::class,
			'mailto'	=> Urn::class,
			'news'		=> Urn::class,
			'isbn'		=> Urn::class,
			'tel'		=> Urn::class,
			'fax'		=> Urn::class,
		);
		
		/**
		 * @return Urn
		**/
		public static function create()
		{
			return new self;
		}
		
		public static function getKnownSubSchemes()
		{
			return array_merge(
                self::$knownSubSchemes,
                HttpUrl::getKnownSubSchemes()
            );
		}

		public function isValid()
		{
			if (
				$this->scheme === null
				|| $this->getAuthority() !== null
			)
				return false;
			
			return parent::isValid();
		}
	}
?>