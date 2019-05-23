<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

include ( "php/common.php" ) ;

$language = get_request ( 'language' , 'commons' ) ;
$project = get_request ( 'project' , 'wikimedia' ) ;
if ( $language == 'commons' ) $project = 'wikimedia' ;
$page = get_request ( 'page' , '' ) ;
$category = get_request ( 'category' , '' ) ;
$depth = get_request ( 'depth' , '0' ) ;
$format = get_request ( 'format' , 'm3u' ) ;

if ( isset ( $_REQUEST['doit'] ) ) {
	$project2 = $project ;
	if ( $language == 'commons' ) $project2 = 'wikipedia' ;
	
#	header('Content-type: text/plain; charset=utf-8');
	$lines = array () ;
	$playlist = "playlist" ;
	if ( $category != '' ) {
		$fs = db_get_images_in_category ( $language , $category , $depth , $project ) ;
		foreach ( $fs AS $f ) {
			if ( strtolower ( substr ( $f , - 4 , 4 ) ) != '.ogg' ) continue ;
			$lines[] = "*$f" ;
		}
		$playlist = str_replace ( ' ' , '_' , $category ) ;
		asort ( $lines ) ;
	} else {
		$page_url = get_wikipedia_url ( $language , $page , 'raw' , $project ) ;
		$text = file_get_contents ( $page_url ) ;
		$lines = explode ( "\n" , $text ) ;
	}
	$playlist = "$language.$project-$playlist" ;
		
	$files = array () ;
	foreach ( $lines AS $l ) {
		if ( substr ( $l , 0 , 1 ) != '*' ) continue ;
		while ( substr ( $l , 0 , 1 ) == '*' ) $l = substr ( $l , 1 ) ;
		$l = trim ( $l ) ;
		$f = '' ;
		$f->filename = $l ;
		$f->title = ucfirst ( trim ( str_replace ( '_' , ' ' , str_ireplace ( '.ogg' , '' , $f->filename ) ) ) ) ;
		$f->language = $language ;
		$f->project = $project ;
		$f->upload_project = $project2 ;
		$f->url = get_image_url ( $f->language , $f->filename , $f->upload_project ) ;
		
		$data_url = "http://{$f->language}.{$f->project}.org/w/api.php?action=query&titles=File:" . urlencode($f->filename) . "&prop=imageinfo&iiprop=metadata&format=php" ;
		$data = unserialize ( file_get_contents ( $data_url ) ) ;

		if ( isset ( $data['query']['pages'] ) ) {
			$data = array_shift ( $data['query']['pages'] ) ;
			$data = $data['imageinfo'][0]['metadata'] ;
			foreach ( $data AS $d ) {
				if ( $d['name'] == 'length' ) {
					$f->length = round ( $d['value'] ) ;
				}
			}
		}

		$files[] = $f ;
	}

	if ( $format == 'm3u' ) {
		header("Content-Type: audio/mpegurl");
		header("Content-Disposition: filename=$playlist.m3u");
		print "#EXTM3U\n" ;
		foreach ( $files AS $f ) {
			print "#EXTINF:" ;
			if ( isset ( $f->length ) ) print $f->length ;
			else print "-1" ;
			print "," . $f->title . "\n" ;
			print $f->url . "\n" ;
		}
	}
	
} else {
	$m3u_checked = $format == 'm3u' ? 'checked' : '' ;
	print get_common_header ( '' , "Wiki2playlist" ) ;
	
	print "<div>Generates a .M3U playlist for audio/video files hosted on Commons, from a Wiki list or category.</div>
	<form method='get' action='./wiki2playlist.php' class='form-inline'><table class='table table-condensed'>
	<tr><th>Project</th><td>
	<input type='text' name='language' value='$language' class='span2' /> .
	<input type='text' name='project' value='$project' class='span4' />
	</td></tr>
	<tr><th>Page with playlist</th><td><input type='text' name='page' value='$page' class='span4' />, <i>or</i></td></tr>
	<tr><th>Category</th><td><input type='text' name='category' value='$category' class='span4' /></td></tr>
	<tr><th>Category depth</th><td><input type='text' name='depth' value='$depth' class='span1' /></td></tr>
	<tr><th>Format</th><td>
	<label><input type='radio' name='format' value='m3u' id='m3u' $m3u_checked /> Extended M3U</label>
	</td></tr>
	<tr><td><a href='http://commons.wikimedia.org/wiki/User:Magnus_Manske/playlist'>Example playlist</a></td><td><input type='submit' name='doit' value='Do it' class='btn btn-primary' /></td></tr>
	</table></form>" ;
	
	print get_common_footer() ;
}
