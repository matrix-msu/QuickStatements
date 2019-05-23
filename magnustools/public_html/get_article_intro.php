<?php

require_once ( "php/common.php" ) ;
require_once ( "legacy.php" ) ;

function dewikify ( $p , $lang ) {
	
	$p = preg_replace ( "/<ref[^>]+\/>/" , '' , $p ) ;
	$p = preg_replace ( "/<ref.+?<\/ref>/" , '' , $p ) ;

	$p = preg_replace ( "/\[\[([^|\]]+)\|([^\]]+)\]\]/" , '$2' , $p ) ;
		
	# Make nice "direct" interlanguage links
	$l2 = "[[" ;
	$p2 = explode ( $l2 , " " . $p ) ;
	$p = trim ( array_shift ( $p2 ) ) . ' ' ;
	foreach ( $p2 AS $part ) {
		$part2 = explode ( ']]' , ' ' . $part ) ;
		if ( count ( $part2 ) > 1 ) {
			$e1 = trim ( array_shift ( $part2 ) ) ;
			$e2 = implode ( $part2 ) ;
//			if ( false === strpos ( $e1 , '|' ) ) $e1 .= '|' ;
			$part = $e1 . $e2 ;
		}
		$p .= $part ;
	}

	# Remove templates
	if ( $lang == 'fr' ) {
	    $f = '/\{\{date\|([^|\}]+)\|([^|\}]+)\|([^|\}]+)\}\}/i' ;
	    $r = '${1} ${2} ${3}' ;
	    $p = preg_replace ( $f , $r , $p ) ;
	}
	
	while ( false !== strpos ( $p , '{{' ) ) {
		$p2 = explode ( '{{' , ' '.$p , 2 ) ;
		$p = substr ( array_shift ( $p2 ) , 1 ) ;
		$p2 = explode ( '}}' , array_pop ( $p2 ) , 2 ) ;
		$p .= array_pop ( $p2 ) ;
	}

	$p = preg_replace ( '/\(\s*\)/' , '' , $p ) ;
	$p = str_replace ( '[]' , '' , $p ) ;
	$p = str_replace ( '}}' , '' , $p ) ;
	
	$p = preg_replace ( '/\([ ,;.]+/' , '(' , $p ) ;
	$p = preg_replace ( '/\s+\)/' , ')' , $p ) ;
	$p = preg_replace ( '/\s*\(\s*\)\s*/' , ' ' , $p ) ;
	
/*	if ( $directlinks ) {
		$ltitle = $ail[$lang] ;
		$p .= " (&rarr;[[:$lang:$ltitle|$ltitle]])" ;
	}*/
	
	$p = preg_replace ( '/\s+/' , ' ' , $p ) ;
	$p = preg_replace ( "/''+/" , '' , $p ) ;
//	$p = preg_replace ( "/\[(http\S+)\s.+?\]/" , '<a href="${1}">${2}</a>' , $p ) ;
	
	return $p ;
}

$lang = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$title = get_request ( 'title' , '' ) ;
$callback = get_request ( 'callback' , '' ) ;

if ( $title == '' ) {

	print get_common_header ( '' , "Get article intro" ) ;
	print "
	<div>Generates a JSONP object with an article intro (first paragraph or so).</div>
	<form method='get' class='form-inline'>
	<table class='table table-condensed'>
	<tr><th>Project</th><td><input type='text' class='span2' name='lang' value='$lang'/>.<input type='text' class='span4' name='project' value='$project'/></td></tr>
	<tr><th>Title</th><td><input type='text' class='span4' name='title' value='$title'/></td></tr>
	<tr><th>Callback</th><td><input type='text' class='span4' name='callback' value='$callback'/> (function to call for JSONP)</td></tr>
	<tr><td/><td><input type='submit' class='btn btn-primary' value='Do it!'/></td></tr>
	</table>
	</form>
	" ;

} else {
	$text = get_wikipedia_article ( $lang , $title , true , $project ) ;
	$p = get_initial_paragraph ( $text , $lang ) ;
	$p = dewikify ( $p , $lang ) ;

	header('Content-type: application/json; charset=utf-8');
	print "$callback(" ;
	//print_r ( $p ) ;
	print json_encode ( $p ) ;
	print ");" ;
}
