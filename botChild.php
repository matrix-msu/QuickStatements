<?php

    ini_set('max_execution_time', 0); // 0 = Unlimited
    ini_set('memory_limit','-1');
    require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

    $starting_row = $argv[1];
    $batch_id = $argv[2];
    $max_num_bots = $argv[3];
    $total_rows = $argv[4];


    function messageDb($msg){
    	$servername = "localhost";
    	$username = "quickstatements";
    	$password = "qsPassw0rd!";
    	$dbname = "quickstatements";
    	$conn = new mysqli($servername, $username, $password, $dbname);
    	if ($conn->connect_error) {
    		die("Connection failed: " . $conn->connect_error);
    	}
    	$sql = "INSERT INTO log (title)
    		 VALUES ('$msg')";

    	if ($conn->query($sql) === TRUE) {
    		echo "New record created successfully";
    	} else {
    		echo "Error: " . $sql . "<br>" . $conn->error;
    	}
    	$conn->close();
    }



    // while ( true ) {
    // 	$qs2 = new QuickStatements ;
    //     messageDb("command start: $starting_row, out of: $total_rows");
    // 	// if ( !$qs2->runNextCommandInBatchFast ( $batch_id, $starting_row ) ){
    // 	$qs2->runNextCommandInBatchFast ( $batch_id, $starting_row );
    //
    //     if($starting_row == $total_rows){
    //         //find next batch and start
    //         messageDb('end of current batch');
    //         //sleep(2);
    //         $nextBatchId = $qs2->findNextBatch($batch_id);
    //         // messageDb('next:'.$nextBatchId);
    //         if( $nextBatchId == '' ){
    //             // messageDb('no next, die');
    //             return 0;
    //         }
    //         sleep(3);
    //         echo exec("/opt/local/bin/php bot_fast.php $nextBatchId true >/dev/null 2>&1 &", $out, $v);
    //     }
    //
    //     $starting_row = $starting_row + $max_num_bots;
    //     if($starting_row > $total_rows){
    //         // messageDb("starting row greater return 0, start: $starting_row, total: $total_rows");
    //         return 0;
    //     }
    //     // }
    //     messageDb("command after if: $starting_row");
    //
    // }

    while ( true ) {
		$qs2 = new QuickStatements ;
		if ( !$qs2->runNextCommandInBatchFast ( $batch_id, $starting_row ) ){
            $starting_row = $starting_row + $max_num_bots;
            if($starting_row > $total_rows){
                return 0;
            }
        }
	}




    return 'return';

 ?>
