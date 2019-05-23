<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

ini_set('memory_limit','500M');
//set_time_limit ( 60 * 10 ) ; // Seconds

include_once ( 'php/common.php' ) ;

$lang = get_request ( 'lang' , 'en' ) ;
$project = get_request ( 'family' , 'wikipedia' ) ;
$categories = get_request ( 'categories' , '' ) ;
$depth = get_request ( 'd' , 3 ) ;

if ( $categories == '' ) {
	print get_common_header ( '' , 'Random article' ) ;
	print "<lead>Loads a random article from a category tree</lead>" ;
	print "<form method='get' class='form-inline'><table class='table table-striped'><tbody>
	<tr><th>Site</th><td><input type='text' name='lang' value='$lang' class='span1' />.<input type='text' name='project' value='$project' class='span2' /></td></tr>
	<tr><th>Category</th><td><input type='text' name='categories' value='$categories' class='span3' />, depth <input type='number' name='d' value='$depth' class='span1' /></td></tr>
	</tbody><tfoot>
	<tr><td/><td><input class='btn btn-primary' type='submit' value='Do it!'> (will auto-forward to random article)</td></tr>
	</tfoot></table></form>" ;
	print get_common_footer() ;
	exit ( 0 ) ;
}

$db = openDB ( $lang , $project ) ;
$pages = getPagesInCategory ( $db , $categories , $depth , 0 , true ) ;
$k = array_rand ( $pages , 1 ) ;
$page = $pages[$k] ;


header('Content-type: text/html');
header("Cache-Control: no-cache, must-revalidate");

print "<html><head>" ;
print "<meta http-equiv='refresh' content='0;url=//$lang.$project.org/wiki/" . urlencode($page) . "'> " ;
print "</head></html>" ;

?>