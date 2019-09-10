<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

ini_set('memory_limit','1500M');
set_time_limit ( 60 * 10 ) ; // Seconds

require_once ( '/matrix/dev/public_html/enslaved-quickstatements/magnustools/public_html/php/ToolforgeCommon.php' ) ;
$tfc = new ToolforgeCommon() ;

function getPagesInCategoryRandom ( $db , $category , $depth = 0 , $namespace = 0 , $no_redirects = false ) {
	global $tfc ;
	$ret = [] ;
	$cats = [] ;
	$tfc->findSubcats ( $db , [$category] , $cats , $depth ) ;
	if ( $namespace == 14 ) return $cats ; // Faster, and includes root category

	$namespace *= 1 ;
	$sql = "SELECT DISTINCT page_title FROM page,categorylinks WHERE cl_from=page_id AND page_namespace=$namespace AND cl_to IN ('" . implode("','",$cats) . "')" ;
	if ( $no_redirects ) $sql .= " AND page_is_redirect=0" ;
	$sql .= " ORDER BY rand() LIMIT 1" ;

	$result = $tfc->getSQL ( $db , $sql ) ;
	while($o = $result->fetch_object()){
		$ret[$o->page_title] = $o->page_title ;
	}
	return $ret ;
}

$lang = 'commons' ;//get_request ( 'lang' , 'en' ) ;
$project = 'wikimedia' ; //get_request ( 'project' , 'wikipedia' ) ;
$category = $tfc->getRequest ( 'category' , '' ) ;
$depth = $tfc->getRequest ( 'd' , 3 ) ;

if ( $category == '' ) {
	print $tfc->getCommonHeader ( '' , 'Random image' ) ;
	print "<lead>Loads a random image from a category tree on Commons</lead>" ;
// 	<tr><th>Site</th><td><input type='text' name='lang' value='$lang' class='span1' />.<input type='text' name='project' value='$project' class='span2' /></td></tr>
	print "<form method='get'><table class='table table-striped'><tbody>
	<tr><th>Category</th><td><input type='text' name='category' value='$category' class='span3' />, depth <input type='number' name='d' value='$depth' class='span1' /></td></tr>
	</tbody><tfoot>
	<tr><td/><td><input class='btn btn-primary' type='submit' value='Do it!'> (will auto-forward to random image)</td></tr>
	</tfoot></table></form>" ;
	print $tfc->getCommonFooter() ;
	exit ( 0 ) ;
}

$db = $tfc->openDB ( $lang , $project ) ;
$pages = getPagesInCategoryRandom ( $db , $category , $depth , 6 , true ) ;
$page = array_pop ( $pages ) ;


header('Content-type: text/html');
header("Cache-Control: no-cache, must-revalidate");

print "<html><head>" ;
print "<meta http-equiv='refresh' content='0;url=//$lang.$project.org/wiki/File:" . urlencode($page) . "'> " ;
print "</head></html>" ;

?>