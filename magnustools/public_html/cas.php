<?PHP

include_once ( 'php/common.php' ) ;

$language = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;

$cas = trim ( get_request ( 'cas' , '' ) ) ;
$title = get_request ( 'title' , '' ) ;

$cas_nodash = str_replace ( '-' , '' , $cas ) ;

$cas9 = $cas_nodash ;
while ( strlen ( $cas9 ) < 9 ) $cas9 = "0$cas9" ;

$html = file_get_contents ( "http://$language.$project.org/w/index.php?action=render&title=Template:CasTemplate" ) ;
$html = str_replace ( '{cas}' , $cas , $html ) ;
$html = str_replace ( '{cas9}' , $cas9 , $html ) ;
$html = str_replace ( '{title}' , $title , $html ) ;

header('Content-type: text/html');
header("Cache-Control: no-cache, must-revalidate");
print get_common_header ( 'cas.php' , 'Chemical Abstracts Service number links' ) ;

if ( $title != "" ) {
	print "<h1>$cas ($title)</h1>" ;
} else {
	print "<h1>$cas</h1>" ;
}

print $html ;

print get_common_footer() ;
