<?PHP

include_once ( 'php/common.php' ) ;

print get_common_header ( "transparent.php" , 'Trans-parent' ) ;
print "<div>Find a translator!</div>" ;

$planguage = get_request ( 'planguage' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$tlanguage = get_request ( 'tlanguage' , '' ) ;

if ( isset ( $_REQUEST['doit'] ) ) {
	$db = openDB ( $planguage,$project ) ;
//	$mysql_con = db_get_con_new($planguage,$project) ;
//	$db = get_db_name ( $planguage , $project ) ;
	$sql = "select distinct rc_user from recentchanges where rc_user>0 order by rc_timestamp desc limit 2000" ;
//	$res = my_mysql_db_query ( $db , $sql , $mysql_con ) ;
	$uids = array() ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
//	while ( $o = mysql_fetch_object ( $res ) ) {
		$uids[] = $o->rc_user ;
	}
	
	$l2 = $tlanguage ;
	make_db_safe ( $l2 ) ;
	$l2 = strtolower ( $l2 ) ;
	$sql = "select /* SLOW_OK NM */ * from page,categorylinks,user where page_namespace=2 and cl_to=\"User_$l2-N\" and cl_from=page_id and page_title=replace(user_name,' ','_') and user_id IN (" . implode(',',$uids) . ")" ;
//	$res = my_mysql_db_query ( $db , $sql , $mysql_con ) ;
	print "<ol>" ;
//	while ( $o = mysql_fetch_object ( $res ) ) {
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
		print "<li><a href='http://$planguage.$project.org/wiki/User:" . $o->page_title . "' target='_blank'>" . $o->user_name . "</a></li>" ;
	}
	print "</ol>" ;
}

print "<form method='post'>" ;
print "<table class='table'>" ;
print "<tr><th>Project Language</th><td><input type='text' size=100 name='planguage' value='$planguage' /></td></tr>" ;
print "<tr><th>Project</th><td><input type='text' size=100 name='project' value='$project' /></td></tr>" ;
print "<tr><th>Other language</th><td><input type='text' size=100 name='tlanguage' value='$tlanguage' /></td></tr>" ;
print "<tr><th/><td><input class='btn btn-primary' type='submit' name='doit' value='Do it' /></td></tr>" ;
print "</table>" ;
print "</form>" ;


print get_common_footer() ;
