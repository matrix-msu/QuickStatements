<?PHP

$out = array ( 'error' => 'OK' , 'data' => array() ) ;
$botmode = isset ( $_REQUEST['botmode'] ) ;
$test = isset ( $_REQUEST['test'] ) ;
if ( $test ) {
	header('Content-type: text/html; charset=UTF-8'); // UTF8 test
	header("Cache-Control: no-cache, must-revalidate");
	print "<html>\n<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8' /></head><body>" ;
} else if ( $botmode ) {
	header ( 'application/json' ) ; // text/plain
} else {
	error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
	ini_set('display_errors', 'On');
}

require_once ( 'php/oauth.php' ) ;
require_once ( 'php/common.php' ) ;


// https://www.mediawiki.org/wiki/Special:OAuthConsumerRegistration/list
$project = 'wikimedia' ;
$language = 'commons' ;
$site = get_request ( 'site' , 'commons.wikimedia.org' ) ;
if ( false !== preg_match ( '/^(.+)\.(.+)\.org$/' , $site , $m ) ) {
	$language = $m[1] ;
	$project = $m[2] ;
}

$oa = new MW_OAuth ( 'magnustools' , $language , $project ) ; // OAuth Uploader

function error ( $e ) {
	global $out , $botmode ;
	if ( $botmode ) {
		$out['error'] = $e ;
	} else {
		print "<pre>" . $e . "</pre>" ;
	}
	return false ;
}

$auth_is_ok = false ;

function checkAuth () {
	global $auth_is_ok , $oa ;
	if ( $auth_is_ok ) return $auth_is_ok ; // Cached, OK
	if ( !$oa->isAuthOK() ) return false ;
	
	$d = $oa->getConsumerRights() ;
	if ( !isset($d->query->userinfo->groups) ) return false ;
	if ( !in_array('autoconfirmed',$d->query->userinfo->groups) ) return false ; # Requires autoconfirmed
	
	if ( isset($_REQUEST['test']) ) {
		print "!!!!<pre>" ;
		print_r ( $d->query->userinfo->groups ) ;
		print "</pre>" ;
	}

	
	$auth_is_ok = true ;
	return true ;
// $oa->getConsumerRights()
}

function setPageText () {
	global $out , $oa , $botmode ;
	if ( !checkAuth() ) return error ( $oa->error ) ;

	$page = trim ( get_request ( "page" , '' ) ) ;
	$text = trim ( get_request ( "text" , '' ) ) ;
	
	if ( $text == '' ) {
		return error ( "No text given" ) ;
	} elseif ( ! $oa->setPageText ( $page , $text ) ) {
		return error ( $oa->error ) ;
	}
}


function uploadFromURL () {
	global $out , $oa , $botmode ;
	if ( !checkAuth() ) return error ( "Auth not OK: " . $oa->error ) ;

	$url = trim ( get_request ( "url" , '' ) ) ;
	$new_file_name = trim ( get_request ( "newfile" , '' ) ) ;
	$desc = trim ( get_request ( "desc" , '' ) ) ;
	$comment = trim ( get_request ( "comment" , '' ) ) ;
	$ignorewarnings = isset ( $_REQUEST['ignorewarnings'] ) ;
	
	if ( $url == '' ) return error ( "No URL given" ) ;
	
	if ( !$oa->doUploadFromURL ( $url , $new_file_name , $desc , $comment , $ignorewarnings ) ) {
		$out['res'] = $oa->last_res ;
		return error ( $oa->error ) ;
	}
	$out['res'] = $oa->last_res ;
}



function bot_out () {
	global $out , $oa , $botmode ;
	if ( !$botmode ) return ;
	if ( isset ( $oa->error ) ) $out['error'] = $oa->error ;
	if ( isset($_REQUEST['callback']) ) print $_REQUEST['callback']."(" ;
	print json_encode ( $out ) ;
	if ( isset($_REQUEST['callback']) ) print ");" ;
}


if ( !$botmode and ( !isset( $_REQUEST['action'] ) or $_REQUEST['action'] != 'authorize' ) ) {
	print get_common_header ( '' , 'OAuth file uploader' ) ;
	print "<p>This tool facilitates file uploads to Wikimedia Commons, under your user name. " ;

	if ( !$oa->isAuthOK() ) {
		print "You will have to <a href='".htmlspecialchars( $_SERVER['SCRIPT_NAME'] )."?action=authorize'>authorise</a> it first.</p>" ;
	} else {
		print "You are logged in as {$oa->userinfo->name}.</p>" ;
	}

}


switch ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '' ) {
	case 'authorize':
		$oa->doAuthorizationRedirect();
		break;
	case 'checkauth':
		if ( !checkAuth() ) error ( "Auth not OK: " . $oa->error ) ;
		else {
			if ( $botmode ) {
				$out['error'] = 'OK' ;
				$out['data'] = $oa->getConsumerRights() ;
			} else print "Auth OK!" ;
		}
		break;
	case 'setpagetext':
		setPageText() ;
		break;
	case 'upload':
		uploadFromURL() ;
		break;
	default:
		if ( !$botmode ) {
			print "<h3>Tools using OAuth Uploader</h3>
<ul>
<li><a href='/commonshelper/index.php'>CommonsHelper</a></li>
<li><a href='/flickr2commons/index.html'>Flickr2Commons</a></li>
<li><a href='/url2commons/index.html'>Url2Commons</a></li>
<li><a href='/geograph2commons/index.html'>Geograph2Commons</a></li>
</ul><br>
See also: <i><a href='https://commons.wikimedia.org/wiki/Special:MyLanguage/Commons:OAuth_Uploader'>Commons:OAuth Uploader</a></i>
" ;
		} else {
			$out['error'] = "Unknown action '$action'" ;
		}
}

if ( $botmode ) {
	bot_out() ;
} else {
	print get_common_footer() ;
}
