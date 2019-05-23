<?PHP

ini_set('user_agent','Magnus tools'); # Fake user agent

$title = $_GET['title'] ;
$lang = $_GET['lang'] ;
$webp = $_GET['webp'] ;

if ( isset ( $title ) and isset ( $lang ) ) {
	$context = stream_context_create(array('http' => array('header'=>'Connection: close\r\n')));
	$p = file_get_contents ( "http://$lang.wikipedia.org/wiki/$title" ,false,$context) ;
	$cnt = preg_match_all ( '/<img (.*?)src="(\/\/upload\..+?)"/' , $p , $m ) ;
	for ( $x = 0 ; $x < $cnt ; $x++ ) {
		$missing = $m[1][$x] ;
		$url = $m[2][$x] ;
//		print "$x | $missing | $url<br/>" ;
//		$img = "BLA" ;

		$type = explode ( "." , $url ) ;
		$type = strtolower ( array_pop ( $type ) ) ;
		if ( isset ( $webp ) ) {
			$type = "webp" ;
			preg_match ( '/\/(\d+)px-/' , $url , $n ) ;
			$px = $n[1] ;
			$img_url = "http://toolserver.org/~magnus/cgi-bin/webp.pl?q=50&sharpness=0&px=$px&thumburl=http:$url" ;
		} else $img_url = "http:$url" ;

		$img = file_get_contents ( $img_url ,false,$context) ;
		$img = base64_encode ( $img ) ;		
		$img = "data:image/$type;base64,$img" ;
		$p = str_replace ( $url , $img , $p ) ;
	}
	print $p ;
	
	
}
