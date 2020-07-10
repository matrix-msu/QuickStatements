<?php

    ini_set('max_execution_time', 0); // 0 = Unlimited
    ini_set('memory_limit','-1');
    require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

    $starting_row = $argv[1];
    $batch_id = $argv[2];
    $max_num_bots = $argv[3];
    $total_rows = $argv[4];

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
