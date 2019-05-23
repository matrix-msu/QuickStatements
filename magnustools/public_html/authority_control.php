<?PHP

header('HTTP/1.1 200 OK');
header('Content-type: application/json; charset=utf-8');
//header('Content-type: text/plain; charset=utf-8');
header('Connection: close');

$opts = array(
 'http'=>array(
  'header' => 'Connection: close'
 )
);
$context = stream_context_create($opts);

$o['status'] = 'OK' ;
if ( $_REQUEST['query'] == 'viaf' ) {
	$key = $_REQUEST['key'] ;
	$url = "http://viaf.org/viaf/search?query=local.names+all+%22" . urlencode($key) . "%22&maximumRecords=10&sortKeys=holdingscount&httpAccept=text/xml" ;
	$o['result'] = file_get_contents ( $url , false , $context ) ;
} elseif ( $_REQUEST['query'] == 'gnd' ) {
	$key = $_REQUEST['key'] ;
	for ( $i = 0 ; $i < 1 ; $i++ ) {
		$t = file_get_contents ( "https://portal.dnb.de/opac.htm?method=showFullRecord&currentResultId=" . urlencode ( $key ) . "%26any%26persons&currentPosition=$i" ) ;
	}
}

if ( isset ( $_REQUEST['callback'] ) ) print $_REQUEST['callback']."(" ;
print json_encode ( $o ) ;
if ( isset ( $_REQUEST['callback'] ) ) print ");" ;
print "\n" ;
