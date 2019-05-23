<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

@set_time_limit ( 10*60 ) ; # Time limit 10min

require_once ( "php/common.php" ) ;

$language = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$page = get_request ( 'page' , '' ) ;
$pages = get_request ( 'pages' , '' ) ;
if ( $pages == '' ) $pages = array() ;
else $pages = explode ( "|" , str_replace ( "\n" , '|' , $pages ) ) ;
if ( $page != '' ) $pages[] = $page ;

if ( count ( $pages ) == 0 ) {

	print get_common_header ( '' , "Get distinct authors" ) ;
	print "
	<div>Generates a distinct list of authors for a set of articles.</div>
	<form method='get' class='form-inline'>
	<table class='table table-condensed'>
	<tr><th>Project</th><td><input type='text' class='span2' name='language' value='$language'/>.<input type='text' class='span4' name='project' value='$project'/> (for wikidata, use \"en.wikidata\")</td></tr>
	<tr><th>Articles</th><td><textarea name='pages' rows='5' cols='80' style='width:100%'>" . implode ( "\n" , $pages ) . "</textarea></td></tr>
	<tr><td/><td><input type='submit' class='btn btn-primary' value='Do it!'/></td></tr>
	</table>
	</form>
	" ;

} else {
	
	$db = openDB ( $language , $project ) ;
	$authors = array () ;

	$p = array() ;
	foreach ( $pages AS $page ) {
		$page = trim ( $page ) ;
		if ( $page == '' ) continue ;
		make_db_safe ( $page ) ;
		$p[] = $page ;
	}
	
#	$sql = "SELECT rev_user_text FROM page,revision_userindex WHERE rev_user > 0 AND rev_page=page_id AND page_title IN (\"" . implode ( '","' , $p ) . "\")" ;
	$sql = "select page_id from page where page_namespace=0 AND page_title IN (\"" . implode ( '","' , $p ) . "\")" ;
	$sql = "SELECT rev_user_text,count(*) AS cnt FROM revision_userindex WHERE rev_user>0 AND rev_page IN ($sql) GROUP BY rev_user_text" ;

#	header('Content-type: text/plain; charset=utf-8'); print $sql ; exit(0);

	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
		$authors[$o->rev_user_text] = $o->cnt * 1 ;
	}

	arsort ( $authors ) ;

	header('Content-type: text/plain; charset=utf-8');
	foreach ( $authors AS $a => $cnt ) print "$a\t($cnt edits)\n" ;
}
