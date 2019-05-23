<?PHP

# ______________________________________________________________________
# BEGIN OF CLASS WikiQuery

# This class offers access to many "properties" of pages, via API
# Its original purpose was to get around the unreliable database connections on the toolserver
# The ToolforgeCommon class likely makes most of the API calls obsolete, but this class is still widely used
# No other dependencies

class WikiQuery {
	public $language , $project ;
	private $retry_api_url = 3 ; # Now many times to try file_get_contents for an API page before giving up

	function WikiQuery ( $language , $project = 'wikipedia' ) {
		if ( $language == 'commons' ) $project = 'wikimedia' ;
		if ( $language == 'wikidata' ) { $project = $language ; $language = 'www' ; }
		if ( $language == "xxx" ) $language = "" ;
		$this->language = $language ;
		$this->project = $project ;
	}

	function urlEncode ( $t ) {
		$t = str_replace ( " " , "_" , $t ) ;
		$t = urlencode ( $t ) ;
		return $t ;
	}

	function get_api_base_url ( $what = '' ) {
	$ret = "http://" ;
	if ( $this->language != '' ) $ret .= "{$this->language}." ;
		$ret .= "{$this->project}.org/w/api.php?format=php&" ;
		if ( $what != '' ) $ret .= 'action=query&prop=' . $what . '&' ;
		return $ret ;
	}

	private function get_api_url ( $what = '' , $append = '' ) {
		return $this->get_api_base_url('what') . $append ;
	}

	function get_result ( $url ) {
		$cnt = $this->retry_api_url ;
		do {
			$result = @file_get_contents ( $url ) ;
			$cnt-- ;
		} while ( $result === false AND $cnt > 0 ) ;
		return unserialize ( $result ) ;
	}

	function get_image_data ( $image , $tw = -1 , $th = -1 ) {
		$url = $this->get_api_base_url ( 'imageinfo' ) ;
		$url .= 'titles=' . $this->urlEncode ( $image ) ;
		$url .= '&iiprop=timestamp|user|comment|url|sha1|size|mime|archivename' ;
		$url .= '&iilimit=50' ;
		if ( $tw > -1 ) $url .= '&iiurlwidth=' . $tw ;
		if ( $th > -1 ) $url .= '&iiurlheight=' . $th ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		$data['id'] = $data['pageid'] ; unset ( $data['pageid'] ) ;
		$data['imghistory'] = $data['imageinfo'] ;
		return $data ;
	}


	function get_existing_pages_sub ( $pages ) {
		$ret = [] ;
		if ( count ( $pages ) == 0 ) return $ret ;
		$add = '' ;
		foreach ( $pages AS $p ) {
			if ( $add != '' ) $add .= '|' ;
			$add .= $this->urlEncode ( $p ) ;
		}
		$url = $this->get_api_base_url ( 'info' ) ;
		$url .= 'titles=' . $add ;
		$data = $this->getSubResults ( $url , ['query','pages'] ) ;
		if ( !isset ( $data ) ) return $ret ;
		foreach ( $data AS $d ) {
			if ( !isset ( $d['pageid'] ) ) continue ;
			if ( $d['pageid'] == 0 ) continue ;
			$ret[] = $d['title'] ;
		}
		return $ret ;
	}

	function get_existing_pages ( $pages ) {
	    $ret = [] ;
	    while ( count ( $pages ) > 0 ) {
	      $temp = [] ;
	      while ( count ( $pages ) > 0 and count ( $temp ) < 200 ) {
	        $temp[] = array_shift ( $pages ) ;
	      }
	      $res = $this->get_existing_pages_sub ( $temp ) ;
	      foreach ( $res AS $r ) $ret[] = $r ;
	    }
	    return $ret ;
	}

	private function getFirstResult ( $url , $path_parts ) {
		$data = $this->getSubResults ( $url , $path_parts ) ;
		if ( !isset($data) or $data === false or count($data) == 0 ) return false ;
		$data = array_shift ( $data ) ;
		return $data ;
	}

	private function getSubResults ( $url , $path_parts ) {
		$data = $this->get_result ( $url ) ;
		if ( !isset($data) or $data === false ) return false ;
		while ( count($path_parts) > 0 ) {
			$part = array_shift ( $path_parts ) ; # First part
			if ( !isset ( $data[$part] ) ) return false ;
			$data = $data[$part] ;
		}
		return $data ;
	}

	function does_image_exist ( $image ) {
		$url = $this->get_api_url ( 'imageinfo' , 'titles=' . $this->urlEncode ( $image ) ) ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !$data ) return false ;
		return ! isset ( $data['missing'] ) ;
	}

	function get_redirect_target ( $title ) {
		$url = $this->get_api_url ( 'info' , "redirects&titles=" . $this->urlEncode ( $title ) ) ;
		$data = $this->get_result ( $url ) ;
		$data = $data['query'] ;
		if ( !isset ( $data['redirects'] ) ) return $data['redirects'] ;
		$data = $data['redirects'] ;
		$data = array_shift ( $data ) ;
		$data = $data['to'] ;
		return $data ;
	}

	function get_categories ( $title ) {
		$ret = [] ;
		$url = $this->get_api_url ( 'categories' , 'cllimit=500&titles=' . $this->urlEncode ( $title ) ) ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !isset ( $data['categories'] ) ) return $ret ; # Error
		$data = $data['categories'] ;
		if ( !is_array ( $data ) ) return $ret ;
		foreach ( $data AS $t ) {
			if ( $t['ns'] != '14' ) continue ; # Not a real category?
			$ret[] = array_pop ( explode ( ':' , $t['title'] , 2 ) ) ;
		}
		return $ret ;
	}

	function get_used_templates ( $title , $return_all_namespaces = false ) {
		$ret = [] ;

		$url = $this->get_api_base_url ( 'templates' ) ;
		$url .= 'titles=' . $this->urlEncode ( $title ) ;
		$url .= '&tllimit=500' ;
		
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !isset ( $data['templates'] ) ) return $ret ;
		$data = $data['templates'] ;
		if ( !is_array ( $data ) ) return $ret ;
		foreach ( $data AS $t ) {
			if ( !$return_all_namespaces AND $t['ns'] != '10' ) continue ; # Not a real template
			$ret[] = array_pop ( explode ( ':' , $t['title'] , 2 ) ) ;
		}
		return $ret ;
	}

	function get_pages_in_category ( $category , $namespace = 0 , $depth = 1 ) {
		if ( $depth <= 0 ) return [] ;
		
		$ret = [] ;

		$url = $this->get_api_base_url () ;
		$url .= 'action=query&list=categorymembers&' ;
		$url .= 'cmtitle=' . $this->urlEncode ( "Category:$category" ) ;
		$url .= '&cmprop=ids|title|timestamp&cmlimit=500' ;
		
		if ( $namespace != -1 ) $url1 = $url . '&cmnamespace=' . $namespace ;
		$url2 = $url . '&cmnamespace=14' ;
		
	//		print "<pre>" ;		print_r ( $url1 ) ;		print "</pre>" ;
		
		# Load pages
		$data = $this->get_result ( $url1 ) ;
		if ( isset ( $data['query'] ) ) $data = $data['query'] ;
		if ( isset ( $data['categorymembers'] ) ) {
			$data = $data['categorymembers'] ;

			foreach ( $data AS $d ) {
				$key = $d['title'] ;
				if ( isset ( $ret[$key] ) ) continue ;
				#if ( $d['ns'] == '14' ) continue ;
				$d['id'] = $d['pageid'] ;
				unset ( $d['pageid'] ) ;
				$ret[$key] = $d ;
			}
		}
		
		if ( $depth == 1 ) return $ret ; # No need to search deeper

		# Load pages in subcategories
		$data = $this->get_result ( $url2 ) ;
		if ( isset ( $data['query'] ) ) $data = $data['query'] ;
		if ( !isset ( $data['categorymembers'] ) ) return $ret ;
		
		$data = $data['categorymembers'] ;
		foreach ( $data AS $d ) {
			$key = $d['title'] ;
			$key = explode ( ':' , $key , 2 ) ;
			$key = array_pop ( $key ) ;
			$subret = $this->get_pages_in_category ( $key , $namespace , $depth - 1 ) ;
			foreach ( $subret AS $k => $v ) {
				if ( isset ( $ret[$k] ) ) continue ; # Have that one already
				$ret[$k] = $v ;
			}
		}

		return $ret ;
	}

	function get_backlinks ( $title , $blfilter = 'all' , $blfilterredir = 'all' ) {
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= '&action=query&list=backlinks&' ;
		$url .= 'bllimit=500&bltitle=' . $this->urlEncode ( $title ) . "&blfilterredir=$blfilterredir" ;
		$data = $this->getSubResults ( $url , ['query','backlinks'] ) ;
		foreach ( $data AS $d ) {
			$ret[$d['title']]['*'] = $d['title'] ;
			if ( $d['ns'] != '0' ) $ret[$d['title']]['ns'] = $d['ns'] ;
			$ret[$d['title']]['id'] = $d['pageid'] ;
		}
		return $ret ;
	}

	function get_images_in_category ( $category , $depth = 1 ) {
		return $this->get_pages_in_category ( $category , 6 , $depth ) ;
	}

	function get_recent_uploads ( $num = 50 ) {
		$url = $this->get_api_base_url () ;
		$url .= "action=query&list=logevents&letype=upload&lelimit=" . $num ;
		return $this->getSubResults ( $url , ['query','logevents'] ) ;
	}

	# All links on a page
	# Returns array of array ( ns , title )
	function get_links ( $title , $ns , $cont = '' ) {
		$ret = [] ;
		$url = $this->get_api_base_url ( 'links' ) ;
		$url .= 'pllimit=500&titles=' . $this->urlEncode ( $title ) ;
		$url .= '&rawcontinue=1' ;
		if ( $cont != '' ) $url .= "&plcontinue=" . $cont ;
		if ( isset ( $ns ) ) $url .= '&plnamespace=' . $ns ;

		$data = $this->get_result ( $url ) ;
		if ( !isset ( $data['query'] ) ) return $ret ;

		$cont = '' ;
		if ( isset($data['query-continue']) and isset($data['query-continue']['links']) ) $cont = $data['query-continue']['links']['plcontinue'] ;

		$data = $data['query'] ;
		$data = $data['pages'] ;
		$data = array_shift ( $data ) ;
		if ( !isset ( $data['links'] ) ) return $ret ;
		$data = $data['links'] ;

		if ( $cont != '' ) {
			$l2 = $this->get_links ( $title , $ns , $cont ) ;
			while ( count($l2) > 0 ) array_push ( $data , array_pop ( $l2 ) ) ;
		}

		return $data ;
	}

	function get_external_links ( $all_titles , $start = '' ) {
		$ret = [] ;
		while ( count ( $all_titles ) > 0 ) {
			$titles = [] ;
			while ( count ( $titles ) < 100 and count ( $all_titles ) > 0 ) $titles[] = array_pop ( $all_titles ) ;
			$url = $this->get_api_base_url ( 'extlinks' ) ;
			foreach ( $titles AS $k => $v ) $titles[$k] = $this->urlEncode ( $v ) ;
			$url .= 'titles=' . implode ( "|" , $titles ) ;

			$data = $this->get_result ( $url ) ;
			if ( !isset ( $data['query'] ) ) return $ret ;
			$data = $data['query'] ;
			$data = $data['pages'] ;
			
			foreach ( $data AS $d ) {
				if ( !isset ( $d['extlinks'] ) ) continue ;
				$curpage = $d['title'] ;
				foreach ( $d['extlinks'] AS $n )
					$url = $n['*'] ;
					if ( substr ( $url , 0 , strlen ( $start ) ) != $start ) continue ; # Those are not the droids we are looking for
					$ret[$url] = $curpage ;
			}
		}

		return $ret ;
	}

	function get_images_on_page ( $title , $check_ignore = 0 ) {
	    $ret = [] ;
	    $imcontinue = '' ;
	    
	    do {
			$url = $this->get_api_base_url ( 'images' ) ;
			$url .= 'imlimit=500&titles=' . $this->urlEncode ( $title ) ;
	//			$url .= '&rawcontinue=1' ; // NO!
			if ( $imcontinue != "" ) $url .= "&imcontinue=" . urlencode ( $imcontinue ) ;

			$data = $this->get_result ( $url ) ;
			if ( isset($data['continue']) and isset($data['continue']['imcontinue']) ) $imcontinue = $data['continue']['imcontinue'] ;
			else $imcontinue = '' ;
			if ( !isset ( $data['query'] ) ) return $ret ;
			$data = $data['query'] ;
			$data = $data['pages'] ;
			$data = array_shift ( $data ) ;
			if ( !isset ( $data['images'] ) ) return $ret ;
			$data = $data['images'] ;
			foreach ( $data AS $i ) {
				$image = $i['title'] ;
				if ( $check_ignore and is_image_ignored ( $image , $this->language , $this->project ) ) continue ;
				$ret[$image] = $image ; # Avoids double listing
			}
		} while ( $imcontinue != '' ) ;

	#		print_r ( $data ) ;
		return $ret ;
	}

	function get_used_image ( $image , $ns = "0" ) {
		$ret = [] ;
		$iucontinue = "" ;
		
		do {
		  $url = $this->get_api_base_url () ;
		  $url .= 'iutitle=' . $this->urlEncode ( $image ) ;
		  $url .= '&iunamespace=' . $this->urlEncode ( $ns ) ;
		  $url .= '&iulimit=500&list=imageusage&action=query' ;
			$url .= '&rawcontinue=1' ;
		  if ( $iucontinue != "" ) $url .= "&iucontinue=" . urlencode ( $iucontinue ) ;

		  $data = $this->get_result ( $url ) ;
		  if ( !isset ( $data['query'] ) ) return $ret ;
		  
		  $iucontinue = "" ;
		  if ( isset ( $data['query-continue'] ) ) {
			$d = $data['query-continue'] ;
			if ( isset ( $d['imageusage'] ) ) {
			  $d = $d['imageusage'] ;
			  $iucontinue = $d['iucontinue'] ;
			}
		  }
		  
		  $data = $data['query'] ;
		  $data = $data['imageusage'] ;

		  foreach ( $data AS $i ) {
			$title = $i['title'] ;
			$ret[$title ] = $title ;
		  }
		} while ( $iucontinue != "" ) ;
		return $ret ;
	}

	function get_namespaces () {
		$url = $this->get_api_base_url () ;
		$url .= "action=query&meta=siteinfo&siprop=namespaces" ;
		$ret = [] ;
		$data = $this->getSubResults ( $url , ['query','namespaces'] ) ;
		foreach ( $data AS $k => $v ) $ret[$k] = $v['*'] ;
		return $ret ;
	}

	function get_url_usage ( $url , $namespace = '' ) {
		$ret = [] ;
		$orig_url = $url ;
		if ( substr ( $url , 0 , 7 ) == 'http://' ) $url = substr ( $url , 7 ) ;
		$wurl = $this->get_api_base_url () ;
		$wurl .= "action=query&list=exturlusage&euquery=" . urlencode ( $url ) ;
		if ( $namespace != '' ) $wurl .= "&eunamespace=" . $namespace ;
		$data = $this->getSubResults ( $url , ['query','exturlusage'] ) ;
		foreach ( $data AS $d ) {
			if ( $d['url'] != $orig_url ) continue ;
		  $ret[] = $d['title'] ;
		}
		return $ret ;
	}


	function get_backlinks_api ( $title , $blfilterredir = 'all' , $namespaces = [] ) {
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&bllimit=500&list=backlinks&bltitle=' . $this->urlEncode ( $title ) . "&blfilterredir=$blfilterredir" ;
		if ( count ( $namespaces ) > 0 ) $url .= '&blnamespace=' . implode ( '|' , $namespaces ) ;
		$data = $this->getSubResults ( $url , ['query','backlinks'] ) ;
		foreach ( $data AS $d ) $ret[] = $d['title'] ;
		return $ret ;
	}

	function get_random_pages ( $number = 1 , $namespaces = array ( 0 ) ) {
		$ret = [] ;
		if ( $number > 10 ) $number = 10 ;
		$url = $this->get_api_base_url () ;
		$url .= "action=query&list=random&rnlimit=$number" ;
	    $url .= "&rnnamespace=" . implode ( '|' , $namespaces ) ;
		$data = $this->getSubResults ( $url , ['query','random'] ) ;
		foreach ( $data AS $k => $d ) $ret[$d['title']] = $d ;
		return $ret ;
	}

	function get_random_page ( $namespaces = array ( 0 ) ) {
		return $this->get_random_pages ( 1 , $namespaces ) ;
	}

	function get_article_url ( $title , $action = '' ) {
		$ret = 'https://' . $this->language . '.' . $this->project . '.org' ;
		if ( $action == '' ) return "$ret/wiki/" . $this->urlEncode ( $title ) ;
		return "$ret/w/index.php?action=$action&title=" . $this->urlEncode ( $title ) ;
	}

	function get_article_link ( $title , $text = '' , $action = '' , $target = '' ) {
		if ( $text == '' ) $text = str_replace ( '_' , ' ' , $title ) ;
		$url = $this->get_article_url ( $title , $action ) ;
		if ( $target != '' ) $target = " target='$target'" ;
		$style = '' ;
		if ( $action == 'edit' ) $style = " style='color:red;'" ;
		return "<a$target$style href='$url'>$text</a>" ;
	}

	function get_page_info ( $title ) {
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&prop=info&titles=' . $this->urlEncode ( $title ) ;
		return $this->getFirstResult ( $url , ['query','pages'] ) ;
	}

	function get_language_links ( $title ) {
		$ret = [] ;
		$url = $this->get_api_url ( '' , 'action=query&prop=langlinks&lllimit=500&redirect=&titles=' . $this->urlEncode ( $title ) ) ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !isset ( $data['langlinks'] ) ) return $ret ;
		$data = $data['langlinks'] ;
		foreach ( $data AS $d ) $ret[$d['lang']] = $d['*'] ;
		return $ret ;
	}

	function get_external_links2 ( $title ) {
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&prop=extlinks&titles=' . $this->urlEncode ( $title ) ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !isset ( $data['extlinks'] ) ) return $ret ;
		$data = $data['extlinks'] ;
		foreach ( $data AS $d ) $ret[] = $d['*'] ;
		return $ret ;
	}

	function get_user_data ( $users ) {
		if ( !is_array ( $users ) ) $users = array ( $users ) ;
		
		$u = [] ;
		foreach ( $users AS $v ) $u[] = $this->urlEncode ( $v ) ;
		
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&list=users&usprop=groups|editcount&ususers=' . implode ( '|' , $u ) ;
		$data = $this->getSubResults ( $url , ['query','users'] ) ;

		foreach ( $data AS $d ) {
			$name = $d['name'] ;
			$r = [] ;
			$r['name'] = $name ;
			$r['editcount'] = $d['editcount'] ;
			if ( isset ( $d['groups'] ) ) {
				foreach ( $d['groups'] AS $v ) $r[$v] = 1 ;
			}
			
			$ret[$name] = $r ;
		}
		return $ret ;
	}

	function get_revisions ( $title , $last = 50 ) {
		$ret = [] ;
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&prop=revisions&rvlimit=' . $last . '&rvprop=ids|user|timestamp|comment|flags&titles=' . $this->urlEncode ( $title ) ;
		$data = $this->getFirstResult ( $url , ['query','pages'] ) ;
		if ( !isset ( $data['revisions'] ) ) return $ret ;
		$data = $data['revisions'] ;
		return $data ;

	}

	function user_exists ( $user ) {
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&list=users&usprop=blockinfo&ususers=' . $this->urlEncode ( $user ) ;
		$data = $this->getFirstResult ( $url , ['query','users'] ) ;
		return ! isset ( $data['missing'] ) ;
	}

	function user_exists_unblocked ( $user ) {
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&list=users&usprop=blockinfo&ususers=' . $this->urlEncode ( $user ) ;
		$data = $this->getFirstResult ( $url , ['query','users'] ) ;
		if ( isset ( $data['missing'] ) ) return false ;
		if ( isset ( $data['blockedby'] ) ) return false ;
		if ( isset ( $data['blockreason'] ) ) return false ;
		return true ;
	}

	function search ( $key , $namespace = '' , $limit = 25 ) {
		$url = $this->get_api_base_url () ;
		$url .= 'action=opensearch&limit=$limit&search=' . urlencode ( $key ) ;
		if ( $namespace != '' ) $url .= "&namespace=" . $namespace ;
		$data = json_decode ( file_get_contents ( $url ) ) ;
		return $data[1] ;
	}

	function fullSearch ( $key , $namespace = '' , $limit = 25 ) {
		$url = $this->get_api_base_url () ;
		$url .= 'action=query&list=search&srlimit=$limit&srsearch=' . urlencode ( $key ) ;
		if ( $namespace != '' ) $url .= "&srnamespace=" . $namespace ;
		$data = unserialize ( file_get_contents ( $url ) ) ;
		$data = $data['query']['search'] ;
		return $data ;
	}


}


# END OF CLASS WikiQuery
# ______________________________________________________________________
