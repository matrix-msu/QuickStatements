<?php

function db_get_user_images ( $username , $db ) {
	$username = $db->real_escape_string ( $username ) ;
	$username = str_replace ( '_' , ' ' , $username ) ;

	$ret = array () ;
	$sql = "SELECT  * FROM image WHERE img_user_text=\"{$username}\"" ;
	$result = getSQL ( $db , $sql ) ;
	while($o = $result->fetch_object()){
		$ret[$o->img_name] = $o ;
	}
	return $ret ;
}


?>