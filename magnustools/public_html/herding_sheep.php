<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');
ini_set('memory_limit','1500M');

require_once ( "php/common.php" ) ;

function format_ts ( $ts ) {
	while ( strlen ( $ts ) < 14 ) $ts .= '0' ;
	return substr($ts,0,4).'-'.substr($ts,4,2).'-'.substr($ts,6,2).' '.substr($ts,8,2).':'.substr($ts,10,2).':'.substr($ts,12,2) ;
}

$language = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$category = get_request ( 'category' , '' ) ;
$manual_users = get_request ( 'users' , '' ) ;
$timestamp = get_request ( 'timestamp' , '' ) ;
$timestamp_stop = get_request ( 'timestamp_stop' , '' ) ;
$limit = get_request ( 'limit' , 500 ) ;

print get_common_header ( '' , 'Herding Sheep' ) ;

print "<div>Common edits from a user group defined by a category</div>
<div>
<form method='get' class='form-inline'>
<table class='table'><tbody>
<tr><th>Project</th><td><input type='text' class='span2' name='language' value='$language'/>.<input type='text' class='span4' name='project' value='$project'/></td></tr>
<tr><th>Category</th><td><input type='text' class='span4' name='category' value='$category'/>, <i>or</i></td></tr>
<tr><th>User list</th><td><textarea name='users'>" . htmlspecialchars ( $manual_users ) . "</textarea></td></tr>
<tr><th>Time range</th><td>
<input type='text' class='span3' name='timestamp_stop' value='$timestamp_stop'/> &mdash; <input type='text' class='span3' name='timestamp' value='$timestamp'/>
(<span style='font-family:Courier'>YYYYMMDDHHMMSS</span>, shorter allowed)</td></tr>
<tr><th>Limit</th><td><input type='number' class='span4' name='limit' value='$limit'/></td></tr>
<tr><td/><td><input type='submit' name='doit' value='Do it' class='btn btn-primary' /></td></tr>
</tbody></table>
</form>
</div>" ;


if ( isset ( $_REQUEST['doit'] ) ) {
	$db = openDB ( $language , $project ) ;
	
	$u2 = array() ;
	if ( $manual_users != '' and $category == '' ) {
		$users = explode ( "\n" , $manual_users ) ;
		
	} else {
		$users = getPagesInCategory ( $db , $category , 9 , 2 ) ;
	}
	foreach ( $users AS $u ) {
		$u2[] = str_replace ( '_' , ' ' , get_db_safe ( trim ( $u ) ) ) ;
	}

	$sql = "SELECT rev_user_text,page_title,page_namespace,rev_timestamp FROM revision_userindex,page WHERE page_namespace=0 AND rev_page=page_id AND rev_user_text IN ('" . implode ( "','" , $u2 ) . "')" ;
	if ( $timestamp != '' ) $sql .= " AND rev_timestamp<='" . get_db_safe ( $timestamp ) . "'" ;
	if ( $timestamp_stop != '' ) $sql .= " AND rev_timestamp>='" . get_db_safe ( $timestamp_stop ) . "'" ;
	$sql .= " ORDER BY rev_timestamp DESC LIMIT " . get_db_safe($limit) ;

	print "<div>" . count ( $u2 ) . " users" ;
	if ( $category != '' ) print " in category <a target='_blank' href='//$language.$project.org/wiki/Category:" . urlencode($category) . "'>$category</a>" ;
	else print " in list" ;
	print ". " ;
	if ( $timestamp == '' ) print "Last $limit edits" ;
	else print "$limit edits since " . format_ts ( $timestamp ) ;
	if ( $timestamp_stop != '' ) print " until " . format_ts ( $timestamp_stop ) ;
	print ":</div>" ;

	print "<div><table class='table table-condensed table-striped'>" ;
	print "<thead><tr><th>Page</th><th>User</th><th>Timestamp</th></thead>" ;
	print "<tbody>" ;
	$cnt = 0 ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
		$page = $o->page_title ;
		$ut = str_replace ( '_' , ' ' , $o->rev_user_text ) ;
		print "<tr><td><a target='_blank' href='//$language.$project.org/wiki/" . urlencode ( $page ) . "'>" . str_replace ( '_' , ' ' , $page ) . "</a></td>" ;
		print "<td><a target='_blank' href='//$language.$project.org/wiki/User:" . urlencode ( str_replace ( ' ' , '_' , $o->rev_user_text ) ) . "'>$ut</a></td>" ;
		print "<td>" . format_ts ( $o->rev_timestamp ) . "</td></tr>" ;
		$last_ts = $o->rev_timestamp ;
		$cnt++ ;
	}
	print "</tbody></table></div>" ;
	
	if ( $cnt == $limit ) {
		$last_ts-- ;
		print "<div><a href='?doit=1&timestamp=$last_ts&language=$language&project=$project&limit=$limit&category=" . urlencode($category) . "'>Next $limit</a></div>" ;
	}
}

print get_common_footer() ;
