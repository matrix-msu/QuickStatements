#!/usr/bin/php
<?PHP
//set_time_limit(0);
ini_set('max_execution_time', 0); // 0 = Unlimited
ini_set('memory_limit','-1');

require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

function iterate() {
	$ret = 0 ;
	$qs = new QuickStatements ;
	$db = $qs->getDB() ;
	$sql = "SELECT id,status FROM batch WHERE status IN ('INIT','RUN')" ;
	if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
	while($o = $result->fetch_object()){
		$qs2 = new QuickStatements ;
		if ( $o->status == 'INIT' ) {
			if ( !$qs2->startBatch ( $o->id ) ) {
				print $qs2->last_error_message."\n" ;
				continue ;
			}
		}
		if ( !$qs2->runNextCommandInBatch ( $o->id ) ) print $qs2->last_error_message."\n" ;
		else $ret++ ;
	}
	return $ret ;
}

function checkAndRunSingleBatch(){
	if ( (isset($argv[1]) and $argv[1] == 'single_batch') || isset($_GET['single_batch']) ) {
		$min_sec_inactive = 60 * 60 ; # 1h
		$min_sec_inactive = 10 ; # start batches after 30 seconds
		$qs = new QuickStatements ;
		$db = $qs->getDB() ;
		$sql = "SELECT * FROM batch WHERE status IN ('INIT','RUN') ORDER BY ts_last_change LIMIT 1" ;
		if(!$result = $db->query($sql)) die('There was an error running the query [' . $db->error . ']');
		$o = $result->fetch_object() ;
		if ( !$o ) {
			echo 'no batches';
			return 0 ; # No batches left
		}
		$ts_last_change = $o->ts_last_change ;
		if ( !preg_match ( '/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/' , $ts_last_change , $m ) ) die ( "Bad time format in ts_last_change batch #{$o->id}\n" ) ;
		$ts_last_change = $m[1].'-'.$m[2].'-'.$m[3].' '.$m[4].':'.$m[5].':'.$m[6] ;
		$diff_sec = time() - strtotime ( $ts_last_change ) ;
	#print "{$ts_last_change}\n{$diff_sec}\n" ;
		//var_dump($diff_sec);die;
		if ( $diff_sec < $min_sec_inactive ) return 0; # Oldest batch is still too young
		print "Using {$o->id}\n" ;

		if ( $o->status == 'INIT' ) {
			if ( !$qs->startBatch ( $o->id ) ) {
				echo 'died';
				print "{$o->id}: " . $qs->last_error_message."\n" ;
				return 0;
			}
		}
		while ( 1 ) {
			$qs2 = new QuickStatements ;
			var_dump($qs2);
			echo '<br>before run command<br>';
			if ( !$qs2->runNextCommandInBatch ( $o->id ) )return 0 ;
			echo '<br>after run command<br>';
			$status = $qs2->getBatchStatus ( [$o->id] ) ;
			if ( $status[$o->id]['batch']->status != 'RUN' ) return 1 ;
		}

		//exit ( 0 ) ;
		return 1;
	}
	echo 'no single batch set';die;
}

$count = 0;
//echo 'hi';die;
while ( 1 ) {
	if( $count == 10 ){
		echo "kept the bot alive for 10 tries";
		die;
	}
	$count++;
	//$worked = iterate() ;
	$worked = checkAndRunSingleBatch() ;
	echo 'worked: '.$worked;
	if ( $worked == 0 ) sleep ( 5 ) ;
	else if ( $worked == 1 ){
		echo 'Finished a batch. Stopping the bot.';die;
	}else sleep ( 1 ) ;
}

?>