<?PHP

ini_set('max_execution_time', 0); // 0 = Unlimited
ini_set('memory_limit','-1');

require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

if( isset($argv[1]) ){
	$_GET['single_batch'] = 1;
	$_GET['id'] = $argv[1];
	$_GET['bypass'] = $argv[2];
}

function checkAndRunSingleBatch(){
	if ( isset($_GET['single_batch']) && isset($_GET['id']) ) {
		$qs = new QuickStatements ;
		$db = $qs->getDB() ;

		$sql = "SELECT * FROM batch WHERE id = " . $_GET['id'];
		$query = $db->query($sql);
		$result = $query->fetch_object();

		if (!$result) {
			echo('Error: Specified id from URL parameter does not exist in the database.<br>');
			die;
		}

		$sql = "SELECT count(*) AS cnt from batch WHERE status='RUN_SEQUENTIAL'" ;
		if(!$result2 = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result2->fetch_object() ;
		if ( $o->cnt > 0 ) {
			echo "There is already a sequential batch running.";
			die;
		}

		if ( $result->status == 'INIT' || $result->status == 'STOP') {
			// queue the batch
			if ( !$qs->startBatch($result->id) ) {
				echo 'died';
				print "{$result->id}: " . $qs->last_error_message."\n" ;
				return 0;
			}
		} else if ($result->status == 'DONE') {
			echo('Error: The specified batch id "'. $_GET['id'] .'" has already been processed (has a status of DONE).');
			die;
		}

		// $isBatchRunning = $qs->checkForRunFastStatus($result->id);
		// if( $isBatchRunning && !isset($_GET['bypass']) ){
		// 	die; // queue the batch instead of injest
		// }
		// $qs->setStatusToRunFast($result->id);
		//
		// $max_num_bots = 40;
		// if( $max_num_bots > $result->total_rows ){
		// 	$max_num_bots = $result->total_rows;
		// }
		//
		// for($i=1;$i<=$max_num_bots;$i++){
		// 	echo exec("/opt/local/bin/php botChildRemove.php $i {$result->id} $max_num_bots {$result->total_rows} >/dev/null 2>&1 &", $out, $v);
		// }
		// $i = $i -1;
		// echo "bots spawned: $i<br>";

		while(1) {
			$qs2 = new QuickStatements;

			if ( !$qs2->runNextCommandInBatchRemove($result->id) )
				return 0;

			$status = $qs2->getBatchStatus( [$result->id] );
			if ( $status[$result->id]['batch']->status != 'RUN_SEQUENTIAL' )
				return 1;
		}

		return 1;
	} else {
		echo '<br>';
		if (!isset($_GET['single_batch']))
			echo 'Error: "single_batch" URL parameter is not set.<br>';
		if (!isset($_GET['id']))
			echo 'Error: "id" URL parameter is not set.<br>';
		die;
	}
}


if ( checkAndRunSingleBatch() ) {
	echo('Started a batch.');
}

?>
