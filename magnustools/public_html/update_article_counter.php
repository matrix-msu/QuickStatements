<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

include_once ( 'php/common.php' ) ;
include_once ( 'legacy.php' ) ;
ini_set('memory_limit','64M');


$language = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$page = get_request ( 'page' , '' ) ;
$depth = 10 ;

//  <span name='articlecount' category='Eifel'>1476</span>

print get_common_header ( '' , 'Update article counter' ) ;


if ( $page != '' ) {
	$sep1 = '<span ' ;
	$sep2 = '</span>' ;
	$url = get_wikipedia_url ( $language , $page , 'raw' , $project ) ;
	$orig_text = file_get_contents ( $url ) ;
	$text = explode ( $sep1 , ' ' . $orig_text ) ;
	$res = array_shift ( $text ) ;
	$res = substr ( $res , 1 ) ;
	foreach ( $text AS $t ) {
		$res .= $sep1 ;
		$s = explode ( '>' , $t , 2 ) ;
		if ( count ( $s ) != 2 ) {
			$res .= $t ;
			continue ;
		}
		if ( !preg_match ( '/articlecount/' , $s[0] ) ) {
			$res .= $t ;
			continue ;
		}
		$res .= $s[0] . '>' ;
		$u = explode ( 'category=' , $s[0] , 2 ) ;
		$u = array_pop ( $u ) ;
		$category = substr ( trim ( $u ) , 1 , -1 ) ;
		
		$s = explode ( $sep2 , $s[1] , 2 ) ;
		
		print "Untersuche Kategorie \"$category\" ... " ; myflush() ;
		
		$done = array () ;
		$db = openDB ( $language , $project ) ;
		$articles = getPagesInCategory ( $db , $category , $depth , 0 ) ;
//		$articles = db_get_articles_in_category ( $language , $category , $depth , 0 , $done , false , '' , $project ) ;
		
		print count ( $articles ) . " Artikel gefunden.<br/>" ;
		
		$res .= count ( $articles ) . $sep2 . $s[1] ;
	}
	
	if ( $res == $orig_text ) {
		print "<b>$page</b> hat sich bezüglich der Artikelzahlen nicht verändert." ;
	} else {
		print cGetEditButton ( $res , $page , $language , $project , "Updating article counter(s)" , "Edit (diff)" , false , false , true , true ) ;
	}
	
	
} else {
	print "
	<div>Counts articles in a WikiProject, and creates an edit button to change the number in the project page (you might have to add an XSS exception).</div>
	<div>Example usage : <pre>http://tools.wmflabs.org/magnustools/update_article_counter.php?language=de&project=wikipedia&page=Portal:Eifel</pre></div>
	<div>The page must contain a tag like this: <pre>&lt;span name='articlecount' category='Eifel'&gt;1476&lt;/span&gt;</pre></div>
	" ;
}


print "</body>" ;
print "</html>\n" ;
myflush() ;
