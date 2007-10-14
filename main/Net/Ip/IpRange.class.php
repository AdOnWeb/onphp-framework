<?php
/***************************************************************************
 *   Copyright (C) 2007 by Vladimir A. Altuchov                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup Ip
	**/
	final class IpRange implements SingleRange
	{
		private $startIp 	= null;
		private $endIp		= null;
		
		/**
		 * @return IpRange
		**/
		public static function create(IpAddress $startIp, IpAddress $endIp)
		{
			return new self($startIp, $endIp);
		}
		
		public function __construct(IpAddress $startIp, IpAddress $endIp)
		{
			if ($startIp->getLongIp() > $endIp->getLongIp())
				throw new WrongArgumentException(
					'start ip must be lower than ip end'
				);
			
			$this->startIp 	= $startIp;
			$this->endIp 	= $endIp;
		}
		
		/**
		 * @return IpAddress
		**/
		public function getStart()
		{
			return $this->startIp;
		}
		
		/**
		 * @return IpAddress
		**/
		public function getEnd()
		{
			return $this->endIp;
		}
		
		public function contains(/*IpAddress*/ $probe)
		{
			return (
				($this->startIp->getLongIp() <= $probe->getLongIp())
				&& ($this->endIp->getLongIp() >= $probe->getLongIp())
			);
		}
	}
?>