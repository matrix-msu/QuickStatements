<?php


    ini_set('max_execution_time', 0); // 0 = Unlimited
    ini_set('memory_limit','-1');
    require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

    //$id = $argv[1];
    $starting_row = $argv[1];
    $batch_id = $argv[2];
    $max_num_bots = $argv[3];
    $total_rows = $argv[4];



    function messageDb($msg){
        $servername = "localhost";
        $username = "christj2";
        $password = "christj2Passw0rd!";
        $dbname = "christj2";
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $sql = "INSERT INTO test (child_id)
             VALUES ('$msg')";

        if ($conn->query($sql) === TRUE) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
        $conn->close();
    }

    //while ( 1 ) {
    	// $qs2 = new QuickStatements ;
    	// //var_dump($qs2);
    	// //echo '<br>before run command<br>';
    	// if ( !$qs2->runNextCommandInBatch ( $batch_id, $starting_row ) ){
        //     $sql = "INSERT INTO test (child_id)
        //     VALUES ('$id-yes')";
        // }else{
        //     $sql = "INSERT INTO test (child_id)
        //     VALUES ('$id-no')";
        // }

        //messageDb($starting_row.'-start');

        $loopCount = 0;
        while ( $loopCount < 1000 ) {
            // $loopCount++;
            //messageDb($starting_row.'-continue');
			$qs2 = new QuickStatements ;
			if ( !$qs2->runNextCommandInBatchFast ( $batch_id, $starting_row ) ){
                $starting_row = $starting_row + $max_num_bots;

                //messageDb($starting_row.'-nextRow..total:'.$total_rows);

                if($starting_row > $total_rows){
                    return 0;
                }
            }
            //return 1;
			//$status = $qs2->getBatchStatus ( [$batch_id] ) ;
			//if ( $status[$batch_id]['batch']->status != 'RUN' ) return 1 ;
		}



    	//echo '<br>after run command<br>';
    	// $status = $qs2->getBatchStatus ( [$batch_id] ) ;
    	// if ( $status[$batch_id]['batch']->status != 'RUN' ) return 1 ;
    //}



    //$id = $starting_row.":".$batch_id;
    // sleep(1);
    // echo 'botchild: '.$id.'.......'.time().'<br>';
    // $servername = "localhost";
    // $username = "christj2";
    // $password = "christj2Passw0rd!";
    // $dbname = "christj2";
    // $conn = new mysqli($servername, $username, $password, $dbname);
    // if ($conn->connect_error) {
    // 	die("Connection failed: " . $conn->connect_error);
    // }
    // // $time = time();
    //
    // if ($conn->query($sql) === TRUE) {
    // 	echo "New record created successfully";
    // } else {
    // 	echo "Error: " . $sql . "<br>" . $conn->error;
    // }
    // $conn->close();

    return 'return';

 ?>
