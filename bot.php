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
	if ( isset($_GET['single_batch']) && isset($_GET['id']) ) {
		$qs = new QuickStatements ;
		$db = $qs->getDB();

		$sql = "SELECT * FROM batch WHERE id = " . $_GET['id'];
		$query = $db->query($sql);
		$result = $query->fetch_object();
		
		if (!$result) {
			echo('Error: Specified id from URL parameter does not exist in the database.<br>');
			die;
		}

		if ( $result->status == 'INIT' || $result->status == 'STOP') {
			// sets the batch status to RUN
			if ( !$qs->startBatch($result->id) ) {
				echo 'died';
				print "{$result->id}: " . $qs->last_error_message."\n" ;
				return 0;
			}
		} else if ($result->status == 'DONE') {
			echo('Error: The specified batch id "'. $_GET['id'] .'" has already been processed (has a status of STOP).');
			die;
		}
		
		while(1) {
			$qs2 = new QuickStatements;
			
			if ( !$qs2->runNextCommandInBatchSequential($result->id) )
				return 0;
			
			$status = $qs2->getBatchStatus( [$result->id] );
			
			if ( $status[$result->id]['batch']->status != 'RUN' )
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

$worked = checkAndRunSingleBatch();
if ($worked == 1) {
	echo('Finished a batch. Stopping the bot.');
}

?>
