<?php

function get_edit_timestamp ( $lang , $project , $title ) {
	$t = "http://{$lang}.{$project}.org/w/index.php?title=Special:Export/" . myurlencode ( $title ) ;
	$t = @file_get_contents ( $t ) ;
#	$desc = $t ;
	$t = explode ( '<timestamp>' , $t , 2 ) ;
	$t = explode ( '</timestamp>' , array_pop ( $t ) , 2 ) ;
	$t = array_shift ( $t ) ;
	$t = str_replace ( '-' , '' , $t ) ;
	$t = str_replace ( ':' , '' , $t ) ;
	$t = str_replace ( 'T' , '' , $t ) ;
	$t = str_replace ( 'Z' , '' , $t ) ;
	return $t ;
}

function cGetEditButton ( $text , $title , $lang , $project , $summary , $button_label , $new_window = true , $add = false , $diff = false , $minor = false , $section = -1 , $blank_init = false ) {
	global $toynote ;
	if ( !isset ( $toynote ) ) $toynote = '' ;
	
	$t = get_edit_timestamp ( $lang , $project , $title ) ;
	if ( $blank_init ) $t = '' ;
	$timestamp = $t ;
	
	$text = str_replace ( "'" , htmlentities ( "'" , ENT_QUOTES ) , $text ) ;
	$summary = str_replace ( "'" , htmlentities ( "'" , ENT_QUOTES ) , $summary.$toynote ) ;

	$url = "//{$lang}.{$project}.org/w/index.php?title=" . myurlencode ( $title ) . '&action=edit' ;
	if ( $add ) $url .= '&section=new' ;
	elseif ( $section >= 0 ) $url .= "&section=$section" ;
	$ncb = "<form id='upload' method=post enctype='multipart/form-data'" ;
	if ( $new_window ) $ncb .= " target='_blank'" ;
	$ncb .= " action='{$url}' style='display:inline'>" ;
	$ncb .= "<input type='hidden' name='wpTextbox1' value='{$text}'/>" ;
	$ncb .= "<input type='hidden' name='wpSummary' value='{$summary}'/>" ;
	if ( $diff ) $ncb .= "<input type='hidden' name='wpDiff' value='wpDiff' />" ;
	else $ncb .= "<input type='hidden' name='wpPreview' value='wpPreview' />" ;
	
	$starttime = date ( "YmdHis" , time() + (12 * 60 * 60) ) ;
	$ncb .= "<input type='hidden' value='{$starttime}' name='wpStarttime' />" ;
	$ncb .= "<input type='hidden' value='{$t}' name='wpEdittime' />" ;

  if ( $minor ) $ncb .= "<input type='hidden' value='1' name='wpMinoredit' />" ;
	if ( $diff ) $ncb .= "<input type='submit' name='wpDiff' value='$button_label'/>" ;
	else $ncb .= "<input class='btn btn-primary' type='submit' name='wpPreview' value='$button_label'/>" ;
	$ncb .= "</form>" ;
	return $ncb ;
}


function get_initial_paragraph ( &$text , $language = '' ) {
	global $image_aliases ;
	$t = explode ( "\n" , $text ) ;
	while ( count ( $t ) > 0 ) {
		$s = trim ( array_shift ( $t ) ) ;
		if ( $s == "" ) continue ;
		if ( substr ( $s , 0 , 2 ) == '{{' ) { # Template
			if ( substr ( $s , -2 , 2 ) == '}}' ) continue ; # One-line template
			while ( count ( $t ) > 0 && substr ( $s , -2 , 2 ) != '}}' ) {
				$s = trim ( array_shift ( $t ) ) ;
			}
			continue ;
		}
		if ( substr ( $s , 0 , 2 ) == '--' ) continue ; # <hr>
		if ( substr ( $s , 0 , 1 ) == ':' ) continue ; # Remark
		if ( substr ( $s , 0 , 1 ) == '*' ) continue ; # List
		if ( substr ( $s , 0 , 1 ) == '#' ) continue ; # List
		if ( substr ( $s , 0 , 1 ) == '=' ) continue ; # Heading
		if ( substr ( $s , 0 , 1 ) == '<' ) continue ; # HTML
		if ( substr ( $s , 0 , 1 ) == '!' ) continue ; # Table fragment
		if ( substr ( $s , 0 , 1 ) == '|' ) continue ; # Table fragment
		if ( substr ( $s , 0 , 2 ) == '|-' ) continue ; # Table fragment
		if ( substr ( $s , 0 , 2 ) == '|}' ) continue ; # Table fragment
		if ( substr ( $s , 0 , 2 ) == '{|' ) { # Table
			while ( count ( $t ) > 0 && substr ( $s , 0 , 2 ) != '|}' ) {
				$s = trim ( array_shift ( $t ) ) ;
			}
			continue ;
		}

		if ( substr ( $s , 0 , 2 ) == '}}' ) continue ; # Template end
		
		$sl = strtolower ( $s ) ;
		
		# Check for images
		foreach ( $image_aliases AS $ia )
			{
			if ( false === strpos ( $sl , '[['.$ia ) ) continue ; # Image
			$sl = '' ;
			break ;
			}
		if ( $sl == '' ) continue ;
		
		if ( false !== strpos ( $sl , "|thumb|" ) ) continue ; # Image
		if ( false !== strpos ( $sl , "|frame|" ) ) continue ; # Image
		if ( false !== strpos ( $sl , "|right|" ) ) continue ; # Image
		if ( false !== strpos ( $sl , "|miniatur|" ) ) continue ; # Image
		if ( false !== strpos ( $sl , "|hochkant=" ) ) continue ; # Image

		if ( $language == 'eo' AND false !== strpos ( $sl , ">>" ) ) continue ; # Esperanto navigation line
		
		# Seems to be a real paragraph
		break ;
	}
	if ( count ( $t ) == 0 ) return "" ;
	return $s ;
}


?>