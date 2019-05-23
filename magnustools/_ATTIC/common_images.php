<?PHP


$bad_images = array () ;
$hard_ignore_images = array () ;

function db_is_image_used_in_templates ( $image , $language , $project ) {
	global $db_bad_images ;
	make_db_safe ( $image ) ;
	$image = array_pop ( explode ( ':' , $image , 2 ) ) ;
	$ikey = $language . "#" . $image ;
#    print "Checking $ikey in bas_images<br/>" ; myflush();
	if ( isset ( $db_bad_images[$ikey] ) ) return true ; # Check cache

	$db = openDB ( $language , $project ) ;
	
	$ret = 0 ;
	$tn = 'CommonImages' ;
	if ( isset ( $toolname ) ) $tn = $toolname ;
	$sql = "SELECT /* SLOW_OK */ page_title,il_to FROM imagelinks,page WHERE il_to='{$image}' AND il_from=page_id AND page_namespace=10 LIMIT 1 /* $tn on $language by Magnus Manske */" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	if($o = $result->fetch_object()){
		if ( isset ( $o->il_to )  ) {
		  $ret = 1 ;
		}
	}

	if ( $ret ) {
#    print "Adding $ikey to bas_images<br/>" ; myflush();
		$db_bad_images[$ikey] = 1 ; # Add to cache
	}
	return $ret ;
}


function is_image_ignored ( $i , $language , $project ) {
#return false ;
	global $hard_ignore_images , $imagetypes , $check_exists , $use_api ;
	
	if ( $use_api ) {
    global $db_bad_images , $wq ;
    if ( isset ( $db_bad_images[$i] ) ) return true ;
    $template_usage = $wq->get_used_image ( $i , "10" ) ;
    print "$i : " . count ( $template_usage ) . "<br/>" ; myflush () ;
    if ( count ( $template_usage ) > 0 ) {
      $db_bad_images[$i] = $i ;
      return true ;
    }
  } else {
    if ( db_is_image_used_in_templates ( $i , $language , $project ) ) return true ;
  }

	$extension = strtolower ( trim ( array_pop ( explode ( '.' , $i ) ) ) ) ;
	if ( !isset ( $imagetypes[$extension] ) OR !$imagetypes[$extension] ) {
#		print "Ignoring {$i} because of type {$extension}<br/>" ;
		return true ;
	}
	
	$nn = substr ( array_pop ( explode ( ":" , $i , 2 ) ) , 0 , 18 ) ;
	$nn = strtolower ( $nn ) ;
	if ( 'replace this image' == $nn ) return true ;

//	return false ;
	
	$i = trim ( $i ) ;
	$i = ucfirst ( trim ( str_replace ( ' ' , '_' , $i ) ) ) ;	
	$where = "{$language}.{$project}" ;
	foreach ( $hard_ignore_images['all'] AS $ii ) {
		if ( fnmatch ( $ii , $i ) ) return true ;
	}
	
	if ( !isset ( $hard_ignore_images[$where] ) )
		return false ;

	foreach ( $hard_ignore_images[$where] AS $ii ) {
		if ( fnmatch ( $ii , $i ) ) return true ;
	}
	
	return false ;
}


function get_flickr_hits ( $title , $flickrresults = 5 ) {
#  $title = str_replace ( ' ' , '+' , 
	$flickr_key = get_flickr_key() ;
	# Search flickr for the possible titles
	$found = array () ;
    $url = "https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key={$flickr_key}&sort=relevance&per_page={$flickrresults}&license=4,5,7,8,9,10&tag_mode=all&extras=tags&text=" . urlencode ( $title ) ;
#    print "TESTING, IGNORE : $url<br/>" ;
    $xml = file_get_contents ( $url ) ;

    $xml = explode ( '<photo ' , $xml ) ;
    array_shift ( $xml ) ;
    foreach ( $xml AS $x ) {
      $o = "" ;
      $x = array_shift ( explode ( '/>' , $x , 2 ) ) . " " ;
      $x = explode ( '" ' , $x ) ;
      foreach ( $x AS $e ) {
        if ( trim ( $e ) == "" ) continue ;
        $e = explode ( '="' , $e ) ;
        $key = trim ( array_shift ( $e ) ) ;
        $value = trim ( array_shift ( $e ) ) ;
        $o->$key = $value ;
      }
      $o->s_url = "//farm{$o->farm}.static.flickr.com/{$o->server}/{$o->id}_{$o->secret}_s.jpg" ;
      $o->page_url = "//flickr.com/photos/{$o->owner}/{$o->id}/" ;
      if ( $o->title == '' ) $o->title = "<i>Unnamed image</i>" ;
      $found[$o->id] = $o ;
    }
    return $found ;
}

function get_flickr_image_table ( $f , $username = "" , $include_flickr_id = false ) {
  global $tusc_user , $tusc_password ;
  $ft = $f->title ;
  if ( $include_flickr_id ) $ft .= " (" . $f->id . ")" ;
  
  $upload = true ;
  $wq = new WikiQuery ( 'commons' , 'wikimedia' ) ;
  $furl = $f->page_url ;
  $furl1 = "http://www.flickr.com" . array_pop ( explode ( 'flickr.com' , $furl , 2 ) ) ;
  $furl2 = "http://flickr.com" . array_pop ( explode ( 'flickr.com' , $furl , 2 ) ) ;
  $data = $wq->get_url_usage ( $furl1 ) ;
  if ( count ( $data ) == 0 ) $data = $wq->get_url_usage ( $furl2 ) ;
  if ( count ( $data ) > 0 ) {
    $upload = false ;
  }

  $ret = "" ;
  $ret .= "<table><tr><td nowrap><a target='_blank' href='{$f->page_url}'><img border='0' src='{$f->s_url}'></a></td><td valign='top'>" ;
  $ret .= "<b>{$f->title}</b><br/>" ;
  
  if ( $upload ) {
  	$image_id = explode ( '/' , $f->page_url ) ;
  	array_pop ( $image_id ) ;
  	$image_id = array_pop ( $image_id ) ;
    $ret .= "Upload to Commons with " ;
    $ret .= "<a target='_blank' href='//wikipedia.ramselehof.de/flinfo.php?id={$f->id}'>flinfo</a> or " ;
    $ret .= "<a target='_blank' href='//tools.wikimedia.de/~bryan/flickr/upload?link={$f->page_url}&username=$username'>Bryan's upload tool</a><br/>" ;
    $ret .= "<form class='form-inline' target='_blank' action='/flickr2commons/' method='get' style='display:inline;margin:0'>" ;
#    $ret .= "Single-click upload to Commons as " ;
#    $ret .= "<input type='text' size='50' name='new_name' value=\"" . $ft . ".jpg\" />" ; // ansi2ascii ( $ft )
    $ret .= "<input type='hidden' name='photoid' value='$image_id' />" ;
#    $ret .= "<input type='hidden' name='gotoeditpage' value='1' />" ;
#    $ret .= "<input type='hidden' name='tusc_user' value='$tusc_user' />" ;
#    $ret .= "<input type='hidden' name='tusc_password' value='$tusc_password' />" ;
    $ret .= " <input class='btn' type='submit' name='doit' value='Upload to Commons using Flickr2Commons' />" ;
    $ret .= "</form><br/>" ;
  } else {
    $d = array_pop ( $data ) ;
    $furl = get_wikipedia_url ( 'commons' , $d , '' , 'wikimedia' ) ;
    $ret .= "<div style='color:red'>This image already exists on Commons as <a target='_blank' href=\"$furl\">$d</a></div>" ;
    $dn = array_pop ( explode ( ':' , $d , 2 ) ) ;
    $dn = explode ( '.' , $dn ) ;
    array_pop ( $dn ) ;
    $dn = implode ( '.' , $dn ) ;
    $ret .= "<tt>[[$d|thumb|$dn.]]</tt><br/>" ;
  }
    
	$ret .= "Tags : $f->tags" ;
  $ret .= "</td></tr></table>" ;
  
  return $ret ;
}
