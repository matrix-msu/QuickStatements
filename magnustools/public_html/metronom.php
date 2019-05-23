<?PHP

//error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
//ini_set('display_errors', 'On');
set_time_limit ( 60 * 10 ) ; // Seconds
//ini_set('memory_limit','1500M');

require_once ( "php/common.php" ) ;

$blocksize = 50 ;

$language = get_request ( 'language' , 'en' ) ;
$project = get_request ( 'project' , 'wikipedia' ) ;
$month = get_request ( 'month' , date('Ym',strtotime("first day of previous month")) ) ;
$user = str_replace ( '_' , ' ' , get_request ( 'user' , '' ) ) ;

print get_common_header ( '' , 'Metronom' ) ;

// <input class='span3' type='text' name='project' value='$project' />

print "<div>
Pageviews for articles <b>you</b> created!
</div>
<form class='inline-form'>
<table class='table table-condensed'>
<tr><th>Wiki</th><td><input class='span2' type='text' name='language' value='$language' />.wikipedia</td></tr>
<tr><th>Month</th><td><input type='text' class='span2' name='month' value='$month' /> (YYYYMM; not before 201508)</td></tr>
<tr><th>User</th><td><input class='span4' name='user' type='text' value='$user' /> <input type='submit' class='btn btn-primary' name='doit' value='Do it!' /></td></tr>
</table>
</form>" ;

myflush();

if ( $user != '' && isset ( $_REQUEST['doit'] ) ) {
	$db = openDB ( $language , $project ) ;
	$sql = "select page_title,rev_timestamp FROM page,revision_userindex where page_id=rev_page and page_is_redirect=0 and page_namespace=0 and rev_user_text='" . $db->real_escape_string($user) . "' and rev_parent_id=0" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	$pages = array() ;
	$creation_date = array() ;
	while($o = $result->fetch_object()){
		$pages[] = $o->page_title ;
		$creation_date[$o->page_title] = substr ( $o->rev_timestamp , 0 , 4 ) . '-' . substr ( $o->rev_timestamp , 4 , 2 ) . '-' . substr ( $o->rev_timestamp , 6 , 2 ) ;
	}
	print "<p>User \"$user\" has started " . count ( $pages ) . " articles on $language.$project.</p>" ;
	print "<p>Checking view stats for $month. This may take a lot of time. Reload will not save you.</p>" ;
	myflush() ;
	
	$year = substr ( $month , 0 , 4 ) ;
	$month = substr ( $month , 4 , 2 ) ;
	$a_date = "$year-$month-01";
	$ldom = date("t", strtotime($a_date));
	$first = "$year$month"."01" ;
	$last = "$year$month$ldom" ;
	
	$views = array() ;
	foreach ( $pages AS $k => $page ) {
		if ( $project == 'wikipedia' ) $wiki = $language ;
		else $wiki = $language.$project ; // Not supported
		
		$url = "https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/$language.$project/all-access/user/".urlencode($page)."/daily/$first/$last" ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		if ( $j === null ) {
			print "<div>Problem accessing view data for <a href='$url'>$page</a>, views will not be counted." ;
			continue ;
		}


		$views[$page] = 0 ;

		foreach ( $j->items AS $i ) {
			$views[$page] += $i->views ;
		}
		
	}
	
	arsort ( $views , SORT_NUMERIC ) ;
	
	$total = 0 ;
	print "<table class='table-condensed table-striped'><thead><tr><th>Page</th><th>Created on</th><th>Views in $year-$month</th></tr></thead><tbody>" ;
	foreach ( $views AS $page => $cnt ) {
		print "<tr>" ;
		print "<td><a target='_blank' href='//$language.$project.org/wiki/$page'>" . str_replace ( '_' , ' ' , $page ) . "</a></td>" ;
		print "<td style='font-family:Courier;font-size:9pt'>" . $creation_date[$page] . "</td>" ;
		print "<td style='text-align:right;font-family:Courier;font-size:9pt'>$cnt</td>" ;
		print "</tr>" ;
		$total += 1*$cnt ;
	}
	print "</tbody><tfoot><tr><th colspan=2>Total views</th><th style='text-align:right;font-family:Courier;font-size:9pt'>$total</th></tr></tfoot></table>" ;
	
}


print get_common_footer() ;
