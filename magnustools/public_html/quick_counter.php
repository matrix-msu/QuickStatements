<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // |E_ALL
ini_set('display_errors', 'On');

require_once ( "php/common.php" ) ;

$user = trim ( get_request ( 'user' , '' ) ) ;
$project = explode ( '.' , get_request ( 'project' , 'en.wikipedia' ) ) ;

print get_common_header('','Quick edit counter') ;

if ( $user == '' or count($project) != 2 ) {
	print "<div class='lead'>Use the same parameters as <a href='/supercount/index.php'>supercount</a>!</div>" ;
	print "<div><form method='get' class='form-inline'>" ;
	print "User <input name='user' type='text' value='" . strip_tags($user) . "' placeholder='User name' /> on <input type='text' name='project' value='" . strip_tags(implode('.',$project)) . "' /> " ;
	print "<input type='submit' value='Show edit counts' class='btb btn-primary' />" ;
	print "</form></div>" ;
} else {
	$db = openDB ( $project[0] , $project[1] ) ;
	$uid = -1 ;
	$sql = "select * from user where user_name='" . $db->real_escape_string($user) . "'" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']'."\n$sql\n");
	while($o = $result->fetch_object()) $uid = $o->user_id ;
	if ( $uid == -1 ) {
		print "No such user \"" . strip_tags($user) . "\"!" ;
		exit ( 0 ) ;
	}
	
	$data = array() ;
	$sql = "select page_namespace,count(*) AS cnt from revision_userindex,page WHERE page_id=rev_page and rev_user=$uid group by page_namespace order by cnt desc" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']'."\n$sql\n");
	while($o = $result->fetch_object()) {
		$data[$o->page_namespace]['rev'] = $o->cnt ;
		$data[$o->page_namespace]['del'] = 0 ;
	}
	
	$sql = "select ar_namespace,count(*) as cnt from archive_userindex where ar_user=$uid group by ar_namespace order by cnt desc" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']'."\n$sql\n");
	while($o = $result->fetch_object()) {
		$data[$o->ar_namespace]['del'] = $o->cnt ;
		if ( !isset($data[$o->ar_namespace]['rev']) ) $data[$o->ar_namespace]['rev'] = 0 ;
	}


	$total = 0 ;
	$t1 = 0 ;
	$t2 = 0 ;
	print "<div class='lead'>Edits by " . strip_tags($user) . " on " . strip_tags(implode('.',$project)) . "</div>" ;
	print "<table class='table table-condensed table-striped' style='width:50%'>" ;
	print "<thead><tr><th>Namespace</th><th>Live</th><th>Deleted</th><th>Total</th></tr></thead><tbody>" ;
	foreach ( $data AS $ns => $o ) {
		$t = $o['rev'] + $o['del'] ;
		print "<tr>" ;
		print "<td>" . $ns . "</td>" ;
		print "<td style='text-align:right;font-family:Courier'>" . number_format($o['rev']) . "</td>" ;
		print "<td style='text-align:right;font-family:Courier'>" . number_format($o['del']) . "</td>" ;
		print "<td style='text-align:right;font-family:Courier'>" . number_format($t) . "</td>" ;
		print "</tr>" ;
		$total += $t ;
		$t1 += $o['rev'] ;
		$t2 += $o['del'] ;
	}
	print "</tbody>" ;
	print "<tfoot><tr><th>Total</th>" ;
	print "<td style='text-align:right;font-family:Courier'>" . number_format($t1) . "</td>" ;
	print "<td style='text-align:right;font-family:Courier'>" . number_format($t2) . "</td>" ;
	print "<td style='text-align:right;font-family:Courier'>" . number_format($total) . "</td>" ;
	print "</tr></tfoot>" ;
	print "</table>" ;
}

print get_common_footer() ;

?>