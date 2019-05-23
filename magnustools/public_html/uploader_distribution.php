<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_COMPILE_ERROR); // E_ALL|
ini_set('display_errors', 'On');
ini_set('memory_limit','500M');
set_time_limit ( 60 * 10 ) ; // Seconds

include_once ( 'php/common.php' ) ;

print get_common_header ( '' , 'Uploader distribution' ) ;

$cat1 = get_request ( 'cat1' , '' ) ;
$cat2 = get_request ( 'cat2' , '' ) ;
$lang = get_request ( 'lang' , 'commons' ) ;
$project = get_request ( 'project' , 'wikimedia' ) ;

print "<form method='get' class='form form-inline inline-form'>Project: " ;
print "<input name='lang' value='$lang' type='text' />" ;
print "<input name='project' value='$project' type='text' />" ;
print "<input type='submit' value='Do it!' name='doit' class='btn btn-primary' />" ;
print "<div><input name='cat1' value='$cat1' type='text' placeholder='Primary category tree root' /></div>" ;
print "<div><textarea style='width:100%' rows='5' name='cat2' placeholder='Categories for intersection (one per row, no prefix)'>$cat2</textarea></div>" ;
print "</form>" ;

if ( isset($_REQUEST['doit']) ) {
	$url = "http://tools.wmflabs.org/quick-intersection/index.php?lang=$lang&project=$project&cats=" . urlencode($cat1."\n".$cat2) . "&ns=6&depth=12&max=30000&start=0&format=html&redirects=none&format=json&sparse=1" ;
	$j = json_decode ( file_get_contents ( $url ) ) ;
	
	$db = openDB ( 'commons' , 'wikimedia' ) ;
	$treecats = getPagesInCategory ( $db , str_replace(' ','_',$cat1) , 20 , 14 , true ) ;
//	print "<pre>" ; print_r ( $treecats ) ; print "</pre>" ;
	
	
	$files = array() ;
	foreach ( $j->pages AS $p ) {
		$p = preg_replace ( '/^.+?:/' , '' , $p ) ;
		$files[] = $db->real_escape_string ( $p ) ;
	}
//	$sql = "SELECT img_user_text,count(*) AS cnt FROM image WHERE img_name IN ('" . implode ( "','" , $files ) . "') group by img_user_text ORDER BY cnt DESC" ;

	$users = array() ;
	$sql = "SELECT img_user_text,(SELECT cl_to FROM page,categorylinks WHERE page_namespace=6 and page_title=img_name AND cl_to IN ('" . implode ( "','" , $treecats ) . "')  AND cl_from=page_id LIMIT 1) AS cat FROM image WHERE img_name IN ('" . implode ( "','" , $files ) . "')" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
		$users[$o->img_user_text][$o->cat]++ ;
//		print "<pre>" ; print_r ( $o ) ; print "</pre>" ;
	}
	$users_sum = array() ;
	foreach ( $users AS $k => $v ) $users_sum[$k] = count($v) ;
	arsort ( $users_sum ) ;
	
	print "<style>span.third { display:none;font-size:8pt; }</style>" ;
	print "<div>" ;
	print "<table class='table table-striped'>" ;
	print "<thead><tr><th>User</th><th># distinct (sub)categories</th><th>Categories (<a href='#' onclick='$(\"span.third\").toggle();return false'>toggle</a>)</th></tr></thead>" ;
	print "<tbody>\n" ;
//	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
//	while($o = $result->fetch_object()){
	foreach ( $users_sum AS $u => $cnt ) {
		print "<tr>" ;
		print "<td><a href='//$lang.$project.org/wiki/User:" . myurlencode($u) . "'>" . $u . "</a></td>" ;
		print "<td style='font-family:courier;text-align:right;'>" . $cnt . "</td>" ;
		print "<td><span class='third'>" . implode("<br/>",array_keys($users[$u])) . "</span></td>" ;
		print "</tr>\n" ;
	}
	print "</tbody></table>" ;
	print "</div>" ;

}


print get_common_footer() ;

?>