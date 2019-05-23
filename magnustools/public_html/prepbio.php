<?PHP

include_once ( 'php/common.php' ) ;

$month_abbrev = array (
	'Jan.' => 'January' ,
	'Feb.' => 'February' ,
	'Mar.' => 'March' ,
	'Apr.' => 'April' ,
	'Jun.' => 'June' ,
	'Jul.' => 'July' ,
	'Aug.' => 'August' ,
	'Sep.' => 'September' ,
	'Sept.' => 'September' ,
	'Oct.' => 'October' ,
	'Nov.' => 'November' ,
	'Dec.' => 'December'
) ;

$infoboxes = array () ;
$infoboxes["person"] = array ( "name", "image", "image_size", "caption", "birth_name", "birth_date", "birth_place", "death_date", "death_place", "death_cause", "residence", "other_names", "known_for", "education", "employer", "occupation", "title", "salary", "networth", "height", "weight", "term", "predecessor", "successor", "party", "boards", "religion", "spouse", "partner", "children", "parents", "relatives", "signature", "website", "footnotes" ) ;
$infoboxes["writer"] = array ( "name", "image", "imagesize", "caption", "pseudonym", "birth_date", "birth_place", "death_date", "death_place", "occupation", "nationality", "period", "genre", "subject", "movement", "debut_works", "influences", "influenced", "signature", "website", "footnotes" ) ;


$wd = array() ;
$wd_api = array() ;

function get_form_row ( $title , $note = '' ) {
	global $wd ;
	$key = strtolower ( str_replace ( ' ' , '' , $title ) ) ;
	$s = '' ;
	if ( isset ( $wd[$key] ) ) $s = $wd[$key] ;
	$value = get_request ( $key , $s ) ;
	return "<tr><th>{$title}</th><td><input type='text' name='{$key}' value='$value' style='width:400px'/></td><td><i>{$note}</i></td></tr>\n" ;
}


function getWdApi ( $url ) {
	global $wd_api ;
	if ( !isset ( $wd_api[$url] ) ) {
		$wd_api[$url] = json_decode ( file_get_contents ( $url ) ) ;
	}
	return $wd_api[$url] ;
}

function setLabel ( $field , $prop , $lang , $j , $all ) {
	global $wd ;
	if ( isset ( $wd[$field] ) ) return ;
	if ( !isset ( $j->claims ) ) return ;
	if ( !isset ( $j->claims->$prop ) ) return ;
	$d = $j->claims->$prop ;
	$labels = array() ;
	foreach ( $d AS $v ) {
		$d2 = $v->mainsnak->datavalue->value ;
		$nid = 'numeric-id' ;
		$q = 'Q' . $d2->$nid ;
		$url = "http://www.wikidata.org/w/api.php?action=wbgetentities&ids=$q&format=json" ;
		$j1 = getWdApi($url) ;
		if ( !isset($j1->entities->$q) ) return ;
		$j2 = $j1->entities->$q ;
		if ( isset ( $j2->labels->$lang ) ) {
			$l = $j2->labels->$lang->value ;
			$labels[] = $l ;
		}
	}
	if ( count($labels) == 0 ) return ;
	if ( $all ) $wd[$field] = implode ( ', ' , $labels ) ;
	else $wd[$field] = $labels[0] ;
}

function setFromDate ( $k1 , $k2 , $prop , $j ) {
	global $wd ;
	if ( isset ( $wd[$k1] ) ) return ;
	if ( isset ( $wd[$k2] ) ) return ;
	if ( !isset ( $j->claims ) ) return ;
	if ( !isset ( $j->claims->$prop ) ) return ;
	$d = $j->claims->$prop ;
	$d2 = $d[0]->mainsnak->datavalue->value ;

	$year = substr ( $d2->time , 0 , 12 ) * 1 ;
	$wd[$k2] = $year ;
	if ( $d2->precision*1 == 11 ) {
		$months = array ( 'January' , 'February' , 'March' , 'April' , 'May' , 'June' , 'July' , 'August' , 'September' , 'October' , 'November' , 'December' ) ;
		$month = substr ( $d2->time , 13 , 2 ) * 1 ;
		$day = substr ( $d2->time , 16 , 2 ) * 1 ;
		$wd[$k1] = $months[$month-1] . " " . $day ;
//		print "<pre>" ; print_r ( $month.'/'.$day ) ; print "</pre>" ;
	}
}

function addAC ( $key , $prop , $j , &$ac ) {
	if ( !isset ( $j->claims ) ) return ;
	if ( !isset ( $j->claims->$prop ) ) return ;
	$d = $j->claims->$prop ;
	$s = $d[0]->mainsnak->datavalue->value ;
	$ac[] = "$key=$s" ;
	return $s ;
}

function seed_from_wd () {
	global $wd ;
	$q = get_request('q','') ;
	if ( $q == '' ) return ;
	$q = 'Q' . preg_replace('/\D/','',$q) ;
	$url = "http://www.wikidata.org/w/api.php?action=wbgetentities&ids=$q&format=json" ;
	$j = json_decode ( file_get_contents ( $url ) ) ;
//	print "<pre>" ; print_r ( $j ) ; print "</pre>" ;
	if ( !isset($j->entities->$q) ) return ;
	$j = $j->entities->$q ;
	
	setFromDate ( 'dayofbirth' , 'yearofbirth' , 'P569' , $j ) ;
	setFromDate ( 'dayofdeath' , 'yearofdeath' , 'P570' , $j ) ;
	
	$ac = array() ;
	addAC ( 'VIAF' , 'P214' , $j , $ac ) ;
	addAC ( 'LCCN' , 'P244' , $j , $ac ) ;
	addAC ( 'ISNI' , 'P213' , $j , $ac ) ;
	addAC ( 'ORCID' , 'P496' , $j , $ac ) ;
	addAC ( 'GND' , 'P227' , $j , $ac ) ;
	addAC ( 'SELIBR' , 'P906' , $j , $ac ) ;
	addAC ( 'BNF' , 'P268' , $j , $ac ) ;
	addAC ( 'BPN' , 'P651' , $j , $ac ) ;
	addAC ( 'RID' , 'P1053' , $j , $ac ) ;
	addAC ( 'BIBSYS' , 'P1015' , $j , $ac ) ;
	addAC ( 'ULAN' , 'P245' , $j , $ac ) ;
	addAC ( 'MBA' , 'P434' , $j , $ac ) ;
	addAC ( 'NLA' , 'P409' , $j , $ac ) ;
	addAC ( 'NDL' , 'P349' , $j , $ac ) ;
	if ( count ( $ac ) > 0 ) {
		$wd['authoritycontrol'] = implode ( '|' , $ac ) ;
	}
	
	$dummy = array() ;
	$i = addAC ( '' , 'P18' , $j , $dummy ) ;
	if ( isset ( $i ) ) $wd['image'] = $i ;
	
	if ( isset ( $j->claims ) ) {
		$urls = array() ;
		foreach ( $j->claims AS $p => $cl ) {
			foreach ( $cl AS $c ) {
				if ( !isset($c->references) ) continue ;
				foreach ( $c->references AS $refs ) {
					foreach ( $refs->snaks AS $rp => $ref ) {
						foreach ( $ref AS $r ) {
							if ( $r->property != 'P854' ) continue ;
							if ( $r->datatype != 'url' ) continue ;
//						print "<pre>" ; print_r ( $r ) ; print "</pre>" ;
							$u = $r->datavalue->value ;
							$urls[$u] = $u ;
						}
					}
				}
			}
		}
		if ( count($urls) > 0 ) {
			$wd['externallinks'] = implode ( "\n" , $urls ) ;
		}
	}

	$langs = array ( 'en','de','fr','es','it','nl' ) ;
	foreach ( $langs AS $lang ) {
		if ( !isset($wd['lastname']) and isset ( $j->labels->$lang ) ) {
			$l = $j->labels->$lang->value ;
			if ( preg_match ( '/^(.+)\s(\S+)$/' , $l , $m ) ) {
				$wd['firstname(s)'] = $m[1] ;
				$wd['lastname'] = $m[2] ;
			}
		}
		if ( !isset($wd['alternativename(s)']) and isset ( $j->aliases->$lang ) ) {
			$l = array() ;
			foreach ( $j->aliases->$lang AS $a ) $l[] = $a->value ;
			$wd['alternativename(s)'] = implode ( "; " , $l ) ;
		}
		if ( !isset($wd['description']) and isset ( $j->descriptions->$lang ) ) {
			$wd['description'] = $j->descriptions->$lang->value ;
		}
		setLabel ( 'occupation' , 'P106' , $lang , $j ) ;
		setLabel ( 'occupations' , 'P106' , $lang , $j , true ) ;
		setLabel ( 'nationality' , 'P27' , $lang , $j ) ;
		setLabel ( 'placeofbirth' , 'P19' , $lang , $j ) ;
		setLabel ( 'placeofdeath' , 'P20' , $lang , $j ) ;
		if ( !isset($wd['description']) ) $wd['description'] = $wd['occupations'] . " from " . $wd['nationality'] ;
	}
}

function get_form () {
	global $wd ;
	seed_from_wd() ;
	$externallinks = $wd['externallinks'] ;
	if ( !isset($externallinks) ) $externallinks = get_request('externallinks','') ;
	$ret = '' ;
	$ret .= '<form method="get" class="form-inline" style="padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid #DDD">Optional: <input style="width:300px" name="q" type="text" placeholder="Qxxx; e.g. Q42 for Douglas Adams"/> <input type="submit" class="btn btn-outline-primary" value="Pre-fill from Wikidata" /></form>' ;
	$ret .= '<form method="post" class="form">' ;
	$ret .= '<table>' ;
	$ret .= get_form_row ( 'Last name' , 'Required' ) ;
	$ret .= get_form_row ( 'First name(s)' ) ;
	$ret .= get_form_row ( 'Alternative name(s)' ) ;
	$ret .= get_form_row ( 'Occupation' , 'Entering XYZ will generate a stub template (XYZ-stub) or a category (XYZs)' ) ;
	$ret .= get_form_row ( 'Nationality' , 'If you enter a nation name here, you can omit it in the description.' ) ;
	$ret .= get_form_row ( 'Description' , 'e.g., "U.S. historian specializing in polar bears" ("U.S." can be omitted if entered above)' ) ;
	$ret .= get_form_row ( 'Day of birth' , 'MONTH DAY; month can be abbreviated' ) ;
	$ret .= get_form_row ( 'Year of birth' ) ;
	$ret .= get_form_row ( 'Place of birth' ) ;
	$ret .= get_form_row ( 'Day of death' , 'MONTH DAY; month can be abbreviated' ) ;
	$ret .= get_form_row ( 'Year of death' ) ;
	$ret .= get_form_row ( 'Place of death' ) ;
	$ret .= get_form_row ( 'Image' ) ;
	$ret .= get_form_row ( 'Authority control' , 'VIAF=xxx|ORCID=xxx|...' ) ;
	$ret .= '<tr><th valign="top">Categories</th><td><textarea name="categories" rows="3" style="width:100%"></textarea></td></tr>' ;
	$ret .= '<tr><th valign="top">External links</th><td><textarea name="externallinks" rows="3" style="width:100%">' . $externallinks . '</textarea></td></tr>' ;
#	$ret .= '<tr><th>Gender</th><td><input type="radio" name="gender" value="male" checked>Male ' .
#			'<input type="radio" name="gender" value="female">Female</td></tr>' ;
  $ret .= '<tr><td/><td colspan="2">' ;
	$ret .= '<label class="checkbox"><input type="checkbox" id="stub" name="stub" value="1"checked>Stub</label> ' ;
	$ret .= '<label class="checkbox"><input type="checkbox" id="create_infobox" name="create_infobox" value="1" checked>Create infobox</label> ' ;
	$ret .= '<label class="checkbox"><input type="checkbox" id="brief_infobox" name="brief_infobox" value="1">Brief infobox</label>' ;
	$ret .= '</td></tr>' ;
	$ret .= '<tr><td/><td><input type="submit" name="doit" value="Generate text" class="btn btn-outline-primary" /></td></tr>' ;
	$ret .= '</table></form>' ;
	$ret .= 'Temporary text copy/paste area (has no effect on generated text)<br/>' ;
	$ret .= '<textarea cols="80" rows="7" style="width:100%"></textarea>' ;
	return $ret ;
}

function q ( $key , $link = false , $extend = '' ) {
	$key = strtolower ( str_replace ( ' ' , '' , $key ) ) ;
	if ( isset ( $_REQUEST[$key] ) ) $ret = trim ( $_REQUEST[$key] ) ;
	else $ret = '' ;
	if ( $ret != '' AND $link ) $ret = "[[" . ucfirst ( $ret ) . "]]" ;
	if ( $ret != '' ) $ret .= $extend ;
	return $ret ;
}

function fix_month ( $date ) {
	global $month_abbrev ;
	$date = ucfirst ( ltrim ( $date ) ) ;
	foreach ( $month_abbrev AS $k => $v ) {
		$k2 = str_replace ( '.' , ' ' , $k ) ;
		if ( false !== strpos ( $date , $k ) ) {
			$date = str_replace ( $k , $v , $date ) ;
			break ;
		}
		if ( false !== strpos ( $date , $k2 ) ) {
			$date = str_replace ( $k2 , $v.' ' , $date ) ;
			break ;
		}
	}
	return $date ;
}

function get_text () {
  global $infoboxes ;
	$ret = '' ;
	
//	$birthdate = fix_month(q('Day of birth',true,', ')) . q('Year of birth',true) ;
//	$deathdate = fix_month(q('Day of death',true,', ')) . q('Year of death',true) ;
	$birthdate = fix_month(q('Day of birth',false,', ')) . q('Year of birth',false) ;
	$deathdate = fix_month(q('Day of death',false,', ')) . q('Year of death',false) ;
	$nation = q('Nationality') ;
	$nationlink = q('Nationality',true) ;
	$stub = isset ( $_REQUEST['stub'] ) ;
	$create_infobox = isset ( $_REQUEST['create_infobox'] ) ;
	$brief_infobox = isset ( $_REQUEST['brief_infobox'] ) ;
	$cats = explode ( "\n" , trim ( $_REQUEST['categories'] ) ) ;
	$occupation = ucfirst ( q('Occupation') ) ;
	$externallinks = explode ( "\n" , trim ( $_REQUEST['externallinks'] ) ) ;
	$birthplace = q('Place of birth',true) ;
	$deathplace = q('Place of death',true) ;
	$lastname = q('Last name') ;
	$altnames = q('Alternative name(s)') ;
#	$gender = $_REQUEST['gender'] ;
	
	# Text
	$ret .= "'''" . q('First Name(s)') . " " . $lastname . "'''" ;
	if ( $altnames != '' ) $ret .= " or '''{$altnames}'''" ;
	
	if ( q('Year of death') != '' ) {
		$ret .= " (" . $birthdate . " &ndash; " . $deathdate . ")" ;
	} else {
		$ret .= " (born " . $birthdate . ")" ;
	}
	
	$desc = trim ( q('Description') ) ;
	if ( $desc != '' ) {
		if ( substr ( $desc , -1 , 1 ) != '.' ) $desc .= '.' ;
		if ( q('Year of death') != '' ) $ret .= ' was ' ;
		else $ret .= ' is ' ;
		
		if ( $nation != '' ) {
			$n = strtolower ( substr ( $nation , 0 , 1 ) ) ;
		} else {
			$n = strtolower ( substr ( $desc , 0 , 1 ) ) ;
		}
		if ( in_array ( $n , array('a','e','i','o','u') ) ) $ret .= 'an ' ;
		else $ret .= 'a ' ;
		$ret .= $nationlink . ' ' ;
		$ret .= $desc . "\n\n" ;
	} else $ret .= ".\n\n" ;
	
	# Birth and Death place
	if ( $birthplace != '' ) $ret .= "{$lastname} was born in {$birthplace}.\n\n" ;
	if ( $deathplace != '' ) $ret .= "{$lastname} died in {$deathplace}.\n\n" ;
	
	# External links
	$exlinks = array() ;
	foreach ( $externallinks AS $ex ) {
		$ex = trim ( $ex ) ;
		if ( $ex == '' ) continue ;
		$exlinks[] = "* [{$ex}]" ;
	}
	if ( count ( $exlinks ) > 0 ) $ret .= "== External links ==\n" . implode ( "\n" , $exlinks ) . "\n\n" ;
	
	
	# Templates and categories
	if ( $stub ) {
		$n2 = ucfirst ( trim ( str_replace ( '.' , '' , $nation ) ) ) ;
		if ( $n2 != '' ) $ret .= "{{{$n2}-bio-stub}}\n" ;
		if ( $occupation != '' ) $ret .= '{{' . $occupation . "-stub}}\n" ;
	}
	
	$ac = get_request ( 'authoritycontrol' , '' ) ;
	if ( $ac != '' ) $ret .= "\n{{Authority control|$ac}}\n" ;
	
	$name2 = trim ( q('Last name') . ", " . q('First Name(s)') ) ;
	$ret .= "\n{{DEFAULTSORT:{$name2}}}\n" ;
	if ( q('Year of birth') != '' ) $ret .= '[[Category:' . q('Year of birth') . " births]]\n" ;
	if ( q('Year of death') != '' ) $ret .= '[[Category:' . q('Year of death') . " deaths]]\n" ;
	if ( q('Year of death') == '' AND q('Day of death') == '' ) $ret .= "[[Category:Living people]]\n" ;
	if ( q('Place of birth') != '' ) $ret .= '[[Category:People from ' . ucfirst ( q('Place of birth') ) . "]]\n" ;
	if ( $occupation != '' ) $ret .= '[[Category:' . $occupation . "s]]\n" ;
	
	foreach ( $cats AS $c ) {
		$c = ucfirst ( trim ( $c ) ) ;
		if ( $c == '' ) continue ;
		$ret .= '[[Category:' . $c . "]]\n" ;
	}
	
	while ( substr ( $desc , -1 , 1 ) == '.' ) $desc = substr ( $desc , 0 , -1 ) ;
/*	
	$ret .= "\n{{Persondata\n" .
		'|NAME=' . $name2 . "\n" .
		'|ALTERNATIVE NAMES=' . $altnames . "\n" .
		'|SHORT DESCRIPTION=' . $desc . "\n" .
		'|DATE OF BIRTH=' . $birthdate . "\n" .
		'|PLACE OF BIRTH=' . q('Place of birth',true) . "\n" .
		'|DATE OF DEATH=' . $deathdate . "\n" .
		'|PLACE OF DEATH=' . q('Place of death',true) . "\n" .
		'}}' ;
*/	
	
	$ret = str_replace ( '  ' , ' ' , $ret ) ;
	
	# Prefix infobox
	if ( $create_infobox ) {
    $ik = trim ( strtolower ( $occupation ) ) ;
    if ( !isset ( $infoboxes[$ik] ) ) $ik = "person" ;
    $infobox = "{{Infobox " . ucfirst ( $ik ) . "\n" ;
    $ib = array () ;
    $il = 0 ;
    
    foreach ( $infoboxes[$ik] AS $k ) {
      $ib[$k] = "" ;
      if ( strlen ( $k ) > $il )  $il = strlen ( $k ) ;
    }
    
    $ib["name"] = trim ( q('First Name(s)') . " " . $lastname ) ;
    $ib["birth_date"] = $birthdate ;
    $ib["birth_place"] = q('Place of birth',true) ;
    $ib["death_date"] = $deathdate ;
    $ib["death_place"] = q('Place of death',true) ;
    $ib["nationality"] = $nationlink ;
    
    $image = get_request ( 'image' , '' ) ;
    if ( $image != '' ) $ib["image"] = $image ;
    
    foreach ( $ib AS $k => $v ) {
      $v = trim ( $v ) ;
      if ( $v == "" && $brief_infobox ) continue ;
      $infobox .= "| " . $k ;
      for ( $n = strlen ( $k ) ; $n < $il ; $n++ ) $infobox .= " " ;
      if ( $v != "" ) $v = " " . $v ;
      $infobox .= " =" . $v . "\n" ;
    }
    $infobox .= "}}\n" ;
    $ret = $infobox . $ret ;
	}
	
	return $ret ;
}


print get_common_header ( '' , "PrepBio" ) ;

if ( isset ( $_REQUEST['doit'] ) ) {
	print "<textarea rows='30' cols='80' style='width:100%'>" . get_text () . "</textarea>" ;
} else {
	print get_form () ;
}

print get_common_footer() ;
