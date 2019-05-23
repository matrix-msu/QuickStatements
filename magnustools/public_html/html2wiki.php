<?php
#
#  HTML to Wiki Converter - tables
#  converts the HTML table tags into their wiki equivalents,
#  which were developed by Magnus Manske and are used in MediaWiki
#
#  Copyright (C) 2004 Borislav Manolov
#
#  This program is free software; you can redistribute it and/or
#  modify it under the terms of the GNU General Public License
#  as published by the Free Software Foundation; either version 2
#  of the License, or (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  Author: Borislav Manolov <b.manolov at gmail.com>
#          http://purl.org/NET/borislav
#############################################################################
#  Adopted to this mirror location and slightly enhanced ("otherhtml" stuff)
#  by Magnus Manske

error_reporting ( E_ALL ) ;
@set_time_limit ( 20*60 ) ; # Time limit 20min

include_once ( "php/common.php") ;


# @param $str    the HTML markup
# @param $row_delim    number of dashes used for a row
# @param $oneline    use one-line markup for cells - ||
# @param $escape    Escape some symbols from the wiki table markup
function html2wiki_tables($str, $row_delim = 1, $oneline = false, $escape = false, $otherhtml = true) {

    $str = str_replace("\r", '', $str);
    if ($escape) {
        $str = strtr($str, array('!'=>'&#33;', '|'=>'&#124;'));
    }

    $my_nl = '=N=';
    $html_tags = array(
        # since PHP 5 str_ireplace() can be used for the end tags
        "/\n/",
        '/>\s+</',         # spaces between tags
        '/<\/table>/i',    # table end
        '/<\/caption>/i',  # caption end
        '/<\/tr>/i',       # rows end
        '/<\/th>/i',       # headers end
        '/<\/td>/i',       # cells end
        # e - replacement string gets evaluated before the replacement
        '/<table([^>]*)>/ie', # table start
        '/<caption>/i',    # caption start
        '/<tr(.*)>/Uie', # row start
        '/<th(.*)>/Uie', # header start
        '/<td(.*)>/Uie', # cell start
        "/\n$my_nl/",
        "/$my_nl/",
        "/\n */",          # spaces at beginning of a line
    );

    $wiki_tags = array(
        " \n",
        '><',        # remove spaces between tags
        "$my_nl|}",      # table end
        '', '', '', '',  # caption, rows, headers & cells end
        "'$my_nl{| '.trim(strip_newlines('$1'))",     # table start
        "$my_nl|+",      # caption
        "'$my_nl|'.str_repeat('-', $row_delim).' '.trim(strip_newlines('$1'))", # rows
        "'$my_nl! '.trim(strip_newlines('$1')).' | '", # headers
        "'$my_nl| '.trim(strip_newlines('$1')).' | '", # cells
        "\n",
        "\n",
        "\n",
    );

    # replace html tags with wiki equivalents
    $str = preg_replace($html_tags, $wiki_tags, $str);

    # remove table row after table start
    $str = preg_replace("/\{\|(.*)\n\|-+ *\n/", "{|$1\n", $str);

    # clear phase
    $s = array('!  |', '|  |', '\\"');
    $r = array('!'   , '|'   ,   '"');
    $str = str_replace($s, $r, $str);

    # use one-line markup for cells
    if ($oneline) {
        $prevcell = false; # the previous row is a table cell
        $prevhead = false; # the previous row is a table header
        $pos = -1;
        while ( ($pos = strpos($str, "\n", $pos+1)) !== false ) { #echo "\n$str\n";
        switch ($str{$pos+1}) {
            case '|': # cell start
                if ($prevcell && $str{$pos+2} == ' ') {
                    $str = substr_replace($str, ' |', $pos, 1); # s/\n/ |/
                } elseif ($str{$pos+2} == ' ') {
                    $prevcell = true;
                } else {
                    $prevcell = false;
                }
                $prevhead = false;
                break;
            case '!': # header cell start
                if ($prevhead) {
                    $str = substr_replace($str, ' !', $pos, 1); # s/\n/ !/
                } else {
                    $prevhead = true;
                }
                $prevcell = false;
                break;
            case '{': # possible table start
                if ($str{$pos+2} == '|') { # table start
                    $prevcell = $prevhead = false;
                } else {
                    $str{$pos} = ' ';
                }
                break;
            default: $str{$pos} = ' ';
        }
        }
    }
    
    # Other HTML
    if ( $otherhtml ) {
      $str = str_ireplace ( '<b>' , "'''" , $str ) ;
      $str = str_ireplace ( '</b>' , "'''" , $str ) ;
      $str = str_ireplace ( '<i>' , "''" , $str ) ;
      $str = str_ireplace ( '</i>' , "''" , $str ) ;
      $str = str_ireplace ( '<p>' , "\n" , $str ) ;
      $str = str_ireplace ( '</p>' , "" , $str ) ;
      $str = str_ireplace ( '<br>' , "\n" , $str ) ;
      $str = str_ireplace ( '<br/>' , "\n" , $str ) ;
      $str = str_ireplace ( '<hr>' , "\n----\n" , $str ) ;
      $str = str_ireplace ( '<hr/>' , "\n----\n" , $str ) ;
      
      $str = str_replace ( "\n\n\n" , "\n\n" , $str ) ;
    }
    
    return $str;
}

function strip_newlines($str) {
    return str_replace("\n", '', $str);
}

function print_form ( $html, $row_delim , $oneline , $escape , $otherhtml , $wiki ) {
  $oneline_yes = $oneline ? "selected=\"selected\"" : '' ;
  $oneline_no = $oneline ? '' : "selected=\"selected\"" ;
  $escape_check = $escape ? "checked=\"checked\"" : '' ;
  $otherhtml_check = $otherhtml ? "checked=\"checked\"" : '' ;
  
  print "<form action=\"./html2wiki.php\"
      method=\"post\" class='form'>
<fieldset>
  <legend>HTML2Wiki Converter<br /></legend>

  <input type=\"hidden\" name=\"post\" value=\"1\" />

  <label for=\"dashes\" accesskey=\"d\" title=\"accesskey 'D'\">
    Number of <span class=\"accesskey\">d</span>ashes used for a row:</label>
  <input type=\"text\" id=\"dashes\" name=\"dashes\"
    value=\"$row_delim\"
    size=\"3\" maxlength=\"2\" class='span1'
    title=\"Enter here the number of dashes used for a row generation, accesskey 'D'\" />
  &nbsp;
  <label for=\"oneline\" accesskey=\"o\" title=\"accesskey 'O'\">
    Use <span class=\"accesskey\">o</span>ne-line position of cells in a row:</label>

  <select id=\"oneline\" name=\"oneline\" class='custom-select'
    title=\"Use one-line position of cells in a row, accesskey 'O'\">
    <option value=\"1\" $oneline_yes>yes</option>
    <option value=\"0\" $oneline_no>no</option>
  </select>

	 <br />
	<input type=\"checkbox\" name=\"escape\" id=\"escape\" $escape_check />
	<label for=\"escape\">Replace the existing signs “!|” with their HTML entities</label>
 &nbsp; 
	<input type=\"checkbox\" name=\"otherhtml\" id=\"otherhtml\" $otherhtml_check />
	<label for=\"otherhtml\">Replace other HTML tags if possible</label>

  <br />

  <label for=\"html\" accesskey=\"h\" title=\"accesskey 'H'\">
    <span class=\"accesskey\">H</span>TML markup:</label> <br />
  <textarea id=\"html\" name=\"html\" class=\"input\"
    title=\"Enter the HTML markup here, accesskey 'H'\"
    rows=\"15\" cols=\"80\" style=\"width:100%\"
    onfocus=\"if (this.value=='Enter the HTML markup here')
            this.value=''\"
    >$html</textarea><br />

  <label for=\"wiki\" accesskey=\"w\" title=\"accesskey 'W'\">

    <span class=\"accesskey\">W</span>iki markup:</label> <br />
  <textarea id=\"wiki\" name=\"wiki\" class=\"output\"
    title=\"Here will be placed the wiki markup, accesskey 'W'\"
    rows=\"15\" cols=\"80\" style=\"width:100%\"
    >$wiki</textarea><br />

  <input type=\"submit\" class=\"btn btn-primary\" value=\"Convert\"
    accesskey=\"x\" title=\"Convert the HTML markup, accesskey 'X'\" />
  <input type=\"reset\" class=\"btn\" value=\"Clear\"
    accesskey=\"r\" title=\"Clear the form, accesskey 'R'\" />
</fieldset>
</form>" ;
}

$html = get_request ( 'html' , '' ) ;
$row_delim = get_request ( 'dashes' , 1 ) ;
$oneline = get_request ( 'oneline' , false ) ;
$escape = isset ( $_REQUEST['escape'] ) ;
$otherhtml = isset ( $_REQUEST['otherhtml'] ) ;

if ( $html != '' ) {
  $wiki = html2wiki_tables($html, $row_delim , $oneline , $escape , $otherhtml ) ;
} else { # Default values
  $wiki = 'Here will be placed the wiki markup' ;
  $html = 'Enter the HTML markup here' ;
  $escape = 1 ;
  $otherhtml = 1 ;
}

print get_common_header ( "html2wiki.php" , "HTML2wiki" ) ;
print "This is a slightly altered mirror of a script by Borislav Manolov, released under the <a href=\"http://www.gnu.org/licenses/gpl-3.0.html\">GPL</a>." ;
print " The original script is <a href=\"http://www.uni-bonn.de/~manfear/html2wiki-tables.php\">here</a>.<br/>" ;
print_form ( $html, $row_delim , $oneline , $escape , $otherhtml , $wiki ) ;
print get_common_footer() ;
