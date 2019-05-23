<?PHP

include_once ( 'php/common.php' ) ;

print "<html><head>" ;
print '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' ;
print "</head><body>" ;
print get_common_header ( 'tab2wiki.php' , 'tab2wiki' ) ;

print "<form method='post' class='form form-inline'>" ;
if ( isset ( $_REQUEST['doit'] ) ) {
	$compressed = isset ( $_REQUEST['compressed'] ) ;
	$colh = isset ( $_REQUEST['colhead'] ) ? 1 : 0 ;
	$rowh = isset ( $_REQUEST['rowhead'] ) ? 1 : 0 ;
	if ( $rowh ) $compressed = 0 ;
	if ( $compressed ) $sep = "||" ;
	else $sep = "\n|" ;
	$text = trim ( get_request ( "text" , "" ) , "\n" ) ;
	$text = str_replace ( "\n" , "\n|-\n|" , $text ) ;
	$text = str_replace ( "\t" , $sep , $text ) ;
	$head = "{| border=\"1\"" ;
	if ( isset ( $_REQUEST['sortable'] ) ) $head .= ' class="sortable"' ;
	$text = $head . "\n|" . $text . "\n|}" ;
	
	if ( $colh ) {
		$t = explode ( "\n|-\n" , $text , 2 ) ;
		$text = array_shift ( $t ) ;
		$text = str_replace ( "\n|" , "\n!" , $text ) ;
		if ( $compressed ) $text = str_replace ( '||' , '!!' , $text ) ;
		else $text = str_replace ( "\n|" , "\n!" , $text ) ;
		$text .= "\n|-\n" . array_pop ( $t ) ;
	}
	
	if ( $rowh ) {
		$text = str_replace ( "\n|-\n|" , "\n|-\n!" , $text ) ;
		if ( !$colh ) {
			$t = explode ( "\n|" , $text , 2 ) ;
			$text = array_shift ( $t ) ;
			$text .= "\n!" . array_shift ( $t ) ;
		}
	}
	
	print "Copy the following into the wiki - done!" ;
	print "<textarea name='text' rows='20' cols='80' style='width:100%'>" ;
	print $text ;
	print "</textarea>" ;
} else {
	print "Copy your (Excel) table, paste it here, click button, magic happens!" ;
	print "<textarea name='text' rows='20' cols='80' style='width:100%'>" ;
	print "</textarea>" ;
	print "<input type='checkbox' name='colhead' value='1' checked /> First element in a column is a header | " ;
	print "<input type='checkbox' name='rowhead' value='1' /> First element in a row is a header | " ;
	print "<input type='checkbox' name='compressed' value='1' checked /> Compress table | " ;
	print "<input type='checkbox' name='sortable' value='1' checked /> Sortable table<br/>" ;
	print "<input class='btn btn-primary' type='submit' name='doit' value='Do it!' />" ;
}
print "</form>" ;



print "</body></html>" ;
