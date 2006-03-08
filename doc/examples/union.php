<?php
/***************************************************************************
 *   Copyright (C) 2005-2006 by Sergey S. Sergeev                          *
 *   webs.support@gmail.com                                                *
 *   	                                                                   *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id: main.inc.php 1188 2006-01-21 03:02:20Z voxus $ */

	require dirname(__FILE__).'/../../global.inc.php.tpl';
	
	$db = DBFactory::getDefaultInstance();
	
	// example queries
	$query1 = OSQL::select()->from('message1')->get(new DBField('id', 'message1'));
	$query2 = OSQL::select()->from('message2')->get(new DBField('id', 'message2'));
	$query3 = OSQL::select()->from('message3')->get(new DBField('id', 'message3'));
	
	// example Factory
	$queryUnion1 = CombineQuery::union($query1, $query2);
	$queryUnion1 = CombineQuery::union($queryUnion1, $query3);
	
	echo "<pre>";
	print_r($queryUnion1->toString($db->getDialect()));
	echo "</pre>";
	
	// example Chain
	$queryUnion2 = CombineQuery::chain();
	$queryUnion2->union($query1);
	$queryUnion2->intersect($query2);
	$queryUnion2->except($query3);
	
	echo "<pre>";
	print_r($queryUnion2->toString($db->getDialect()));
	echo "</pre>";
	
	// example Block
	$queryUnion3 = CombineQuery::exceptBlock($query1, $query2, $query3);
	echo "<pre>";
	print_r($queryUnion3->toString($db->getDialect()));
	echo "</pre>";
	
	// example composite
	$queryUnion4 = CombineQuery::union($query1, $queryUnion3);
	echo "<pre>";
	print_r($queryUnion4->toString($db->getDialect()));
	echo "</pre>";
	
	$query5 =
		OSQL::select()->
		get(new DBField('id', 'foo'))->
		from($queryUnion3, "foo");
	
	echo "<pre>";
	print_r($query5->toString($db->getDialect()));
	echo "</pre>";
	
	$query6 =
		OSQL::select()->
		get(new DBField('id', 'messages'))->
		from('messages')->where(
			Expression::in('id', $queryUnion3)
		);
	
	echo "<pre>";
	print_r($query6->toString($db->getDialect()));
	echo "</pre>";
	
	$query7 =
		OSQL::select()->
		get(new DBField('id', 'messages'))->
		from(CombineQuery::union($query1, $query2), 'foo');
	
	echo "<pre>";
	print_r($query7->toString($db->getDialect()));
	echo "</pre>";
?>