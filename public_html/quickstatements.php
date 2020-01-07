<?PHP

/*
To use for editing in a tool (requires a bot.ini file, see below):

require_once ( __DIR__ . '/public_html/quickstatements.php' ) ;

function getQS () {
	$toolname = '' ; // Or fill this in manually
	$path = realpath(dirname(__FILE__)) ;
	$user = get_current_user() ;
	if ( $toolname != '' ) {}
	else if ( preg_match ( '/^tools\.(.+)$/' , $user , $m ) ) $toolname = $m[1] ;
	else if ( preg_match ( '/^\/mnt\/nfs\/[^\/]+\/([^\/]+)/' , $path , $m ) ) $toolname = $m[1] ;
	else if ( preg_match ( '/^\/data\/project\/([^\/]+)/' , $path , $m ) ) $toolname = $m[1] ;
	if ( $toolname == '' ) die ( "getQS(): Can't determine the toolname for $path\n" ) ;
	$qs = new QuickStatements() ;
	$qs->use_oauth = false ;
	$qs->config->bot_config_file = "/data/project/$toolname/bot.ini" ;
	$qs->toolname = $toolname ;
//	$qs->sleep = 1 ; // Sleep 1 sec between edits
	return $qs ;
}

$qs = getQS() ;
$commands = "Q123\tP456\tQ789" ; // Just QuickStatements V1 commands, can be multiple lines with "\n"
$tmp = $qs->importData ( $commands , 'v1' ) ;
$qs->runCommandArray ( $tmp['data']['commands'] ) ;


A bot.ini file needs to exist in your tool home directory, looking like this:
[user]
user = YourBotName
pass = YourBotPassword
*/

require_once ( __DIR__ . '/../magnustools/public_html/php/common.php' ) ;
require_once ( __DIR__ . '/../magnustools/public_html/php/wikidata.php' ) ;
require_once ( __DIR__ . '/../magnustools/public_html/php/oauth.php' ) ;
require_once ( __DIR__ . '/../vendor/autoload.php' ) ;

// QuickStatements class

class QuickStatements {

	public $last_item = '' ;
	public $wd ;
	public $oa ;
	public $config ;
	public $use_oauth = true ;
	public $last_error_message = '' ;
	public $toolname = '' ; // To be set if used directly by another tool
	public $sleep = 0.1 ; // Number of seconds to sleep between each edit
	public $use_command_compression = false ;
	public $bot_config_file = '' ; // Legacy, should be in config.json
	public $temporary_batch_id ;
	public $retry_on_database_lock = false ;
	public $use_user_oauth_for_batch_edits = true ;

	protected $actions_v1 = array ( 'L'=>'label' , 'D'=>'description' , 'A'=>'alias' , 'S'=>'sitelink' ) ;
	protected $is_batch_run = false ;
	protected $user_name = '' ;
	protected $user_id = 0 ;
	protected $user_groups = array() ;
	protected $db ;
	protected $logging = false ;

	public function __construct () {
		global $wikidata_api_url ;
		$this->config = json_decode ( file_get_contents ( __DIR__ . '/config.json' ) ) ;
//		echo 'hi';
//		var_dump(json_decode ( file_get_contents ( __DIR__ . '/config.json' ) ));
//		var_dump(__DIR__ . '/config.json');
//		var_dump($this->config);
//		die;
		$wikidata_api_url = $this->getSite()->api ;
		$this->wd = new WikidataItemList () ;
	}
/*
	public function setBatchRun ( $is_batch ) {
		$this->is_batch_run = $is_batch ;
	}
*/

	public function generateTemporaryBatchID () {
		return '#temporary_batch_' . (round(microtime(true) * 1000)) ;
	}

	public function generateAndUseTemporaryBatchID () {
		$this->temporary_batch_id = $this->generateTemporaryBatchID() ;
		return $this->temporary_batch_id ;
	}

	protected function isBatchRun () {
		return $this->is_batch_run ;
	}

	public function getOA() {
		if ( !isset($this->oa) ) {
			$toolname = 'quickstatements' ; // TODO override with $this->toolname?

			$params = [
				'tool' => $toolname ,
				'language' => $this->getSite()->oauth->language ,
				'project' => $this->getSite()->oauth->project ,
				'ini_file' => $this->getSite()->oauth->ini_file ,
				'mwOAuthUrl' => $this->getSite()->oauth->mwOAuthUrl ,
				'mwOAuthIW' => $this->getSite()->oauth->mwOAuthIW ,
				'apiUrl' => $this->getSite()->api
			] ;
			if ( isset($this->getSite()->oauth->publicMwOAuthUrl) ) $params['publicMwOAuthUrl'] = $this->getSite()->oauth->publicMwOAuthUrl ;
			$this->oa = new MW_OAuth ( $params ) ;
		}
		return $this->oa ;
	}

	public function setOA( MW_OAuth $oa ) {
		if ( isset($this->oa) ) {
			$this->log( 'Overriding one OAuth object with another, this is unexpected' );
		}
		$this->oa = $oa;
	}

	public function getBatch ( $id ) {
		$id *= 1 ;
		$ret = array('commands'=>array()) ;
		$db = $this->getDB() ;
		$sql = "SELECT * FROM command WHERE batch_id=$id" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) {
			$j = json_decode ( $o->json ) ;
			$j->_meta = $o ;
			unset ( $j->_meta->json ) ;
			$ret['commands'][$o->num] = $j ;
		}
		return $ret ;
	}

	public function importData ( $data , $format , $persistent = false ) {
		$ret = array ( "status" => "OK" ) ;
		$format = trim ( strtolower ( $format ) ) ;
		// TODO persistent
		if ( $format == 'v1' ) $this->importDataFromV1 ( $data , $ret ) ;
		elseif ( $format == 'csv' ) $this->importDataFromCSV ( $data , $ret ) ;
		else $ret['status'] = "ERROR: Unknown format $format" ;
		return $ret ;
	}

	public function getCurrentUserID () {
		$oa = $this->getOA() ;
		$cr = $oa->getConsumerRights() ;
		if ( !isset($cr->query) || !isset($cr->query->userinfo) || !isset($cr->query->userinfo) || !isset($cr->query->userinfo->id) ) {
			$this->last_error_message = "Not logged in" ;
			return false ;
		}
		$this->user_name = $cr->query->userinfo->name ;
		$this->user_id = $cr->query->userinfo->id ;
		$this->user_groups = $cr->query->userinfo->groups ;
		if ( !$this->ensureCurrentUserInDB() ) return false ;
		return $this->user_id ;
	}

	public function addBatch ( $commands , $user_id , $name = '' , $site = '' ) {
		if ( $site == '' ) $site = $this->config->site ;
		if ( count($commands) == 0 ) return $this->setErrorMessage ( 'No commands' ) ;
		if ( $this->use_command_compression ) $commands = $this->compressCommands ( $commands ) ;
		$db = $this->getDB() ;
		$ts = $this->getCurrentTimestamp() ;
		$sql = "INSERT INTO batch (name,user,site,ts_created,ts_last_change,status) VALUES ('".$db->real_escape_string($name)."',$user_id,'".$db->real_escape_string($site)."','$ts','$ts','LOADING')" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		$batch_id = $db->insert_id ;
		$sql = "INSERT INTO batch_oauth (batch_id,serialized) VALUES ($batch_id,'".$db->real_escape_string(serialize($this->getOA()))."')" ;
		if(!$result = $db->query($sql)) $this->log( "Could not store OAuth information for batch {$batch_id} [{$db->error}]" );
		$totalRows = 0;
		$sql = "CREATE TABLE `command_{$batch_id}` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `batch_id` int(11) NOT NULL,
			  `num` int(11) NOT NULL,
			  `row_num` int(11) NOT NULL,
			  `json` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
			  `status` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			  `message` varchar(1000) COLLATE utf8mb4_unicode_ci,
			  `ts_change` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			  PRIMARY KEY (`id`),
			  KEY `batch_id` (`batch_id`),
			  KEY `batch_id_2` (`batch_id`,`status`)
	  		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

		if(!$result = $db->query($sql)) $this->log( "Could not create command table for batch {$batch_id} [{$db->error}]" );
		foreach ( $commands AS $k => $c ) {
			$cs = json_encode ( $c ) ;
			if ( trim($cs) == '' ) continue ; // Paranoia
			$status = 'INIT' ;
			$rowNum = 1;
			// var_dump($c);
			// var_dump($c->rowNum);
			if ( isset($c['rowNum']) ){
				//echo "rowNum set:";
				$rowNum = $c['rowNum'];
				if( $rowNum > $totalRows ){
					$totalRows = $rowNum;
				}
			}
			if ( isset($c->status) and trim($c->status) != '' ) $status = strtoupper(trim($c['status'])) ;
			$sql = "INSERT INTO command_{$batch_id} (batch_id,num,json,status,ts_change,row_num) VALUES ($batch_id,$k,'".$db->real_escape_string($cs)."','".$db->real_escape_string($status)."','$ts',$rowNum)" ;
			//echo $sql;die;
			if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		// echo 'set total rows here:';
		// var_dump($totalRows);
		// die;
		$sql = "UPDATE batch SET status='INIT', total_rows=$totalRows WHERE id=$batch_id" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return $batch_id ;
	}

	public function getDB () {
		if ( !isset($this->db) or !$this->db->ping() ) {
			$this->db = openToolDB ( 'quickstatements_p' ) ;
			$this->db->set_charset("utf8") ;
		}
		return $this->db ;
	}

	public function getAPI () {
		return $this->getSite()->api ;
	}

	public function getToolBase () {
		return $this->getSite()->toolBase ;
	}

	public function isUserBlocked ( $username ) {
		$username = ucfirst ( str_replace ( ' ' , '_' , trim ( $username ) ) ) ;
		$url = $this->getAPI() . "?action=query&list=blocks&format=json&bkusers=" . urlencode ( $username ) ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		foreach ( $j->query->blocks AS $b ) {
			//if ( $username == $b->user )
			return true ; // We asked for a specific user, so if there is a value, it must be the user, right?
		}
		return false ;
	}

	public function getUsernameFromBatchID ( $batch_id ) {
		$db = $this->getDB() ;
		$user_name = '' ;
		$sql = "select user.name AS `name` from `user`,batch WHERE user.id=batch.user AND batch.id=$batch_id"  ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) $user_name = $o->name ;
		return $user_name ;
	}

	public function findNextBatch ( $batch_id ) {
		$db = $this->getDB() ;
		// $userId = '' ;
		// $sql = "select user from batch WHERE id=$batch_id"  ;
		// if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		// while ( $o = $result->fetch_object() ) $userId = $o->user;

		$sql = "select * from batch WHERE status='QUEUED' and id<>'$batch_id' order by ts_last_change ASC limit 1";
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ){
			$batch = $o;
		}
		return $batch->id ;
	}

	public function checkForRunFastStatus ( $batch_id ) {
		$db = $this->getDB() ;

		$sql = "SELECT count(*) AS cnt from batch WHERE status='RUN_FAST'" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result->fetch_object() ;
		if ( $o->cnt > 0 ) {
			return true;
		}
		return false ;
	}

	public function setStatusToRunFast ( $batch_id ) {
		$batch_id *= 1 ;
		if ( isset ( $qs_global_bot_api ) ) unset ( $qs_global_bot_api ) ;
		if ( isset ( $this->bot_api ) ) unset ( $this->bot_api ) ;
		$db = $this->getDB() ;

		$user_name = $this->getUsernameFromBatchID ( $batch_id ) ;
		if ( $user_name == '' ) return $this->setErrorMessage ( "Cannot determine user name for batch #" . $batch_id ) ;
		if ( $this->isUserBlocked ( $user_name ) ) {
			$sql = "UPDATE batch SET status='BLOCKED' WHERE id=$batch_id" ;
			$db->query($sql) ;
			return $this->setErrorMessage ( "User:$user_name is blocked on Wikidata" ) ;
		}

		$sql = "UPDATE batch SET status='RUN_FAST' WHERE id=$batch_id";
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return true ;
	}

	public function queueBatch ( $batch_id ) {
		$batch_id *= 1 ;
		if ( isset ( $qs_global_bot_api ) ) unset ( $qs_global_bot_api ) ;
		if ( isset ( $this->bot_api ) ) unset ( $this->bot_api ) ;
		$db = $this->getDB() ;

		$user_name = $this->getUsernameFromBatchID ( $batch_id ) ;
		if ( $user_name == '' ) return $this->setErrorMessage ( "Cannot determine user name for batch #" . $batch_id ) ;
		if ( $this->isUserBlocked ( $user_name ) ) {
			$sql = "UPDATE batch SET status='BLOCKED' WHERE id=$batch_id" ;
			$db->query($sql) ;
			return $this->setErrorMessage ( "User:$user_name is blocked on Wikidata" ) ;
		}

		$sql = "UPDATE batch SET status='QUEUED' WHERE id=$batch_id";
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return true ;
	}

	public function startBatch ( $batch_id ) {
		$batch_id *= 1 ;
		if ( isset ( $qs_global_bot_api ) ) unset ( $qs_global_bot_api ) ;
		if ( isset ( $this->bot_api ) ) unset ( $this->bot_api ) ;
		$db = $this->getDB() ;

		$user_name = $this->getUsernameFromBatchID ( $batch_id ) ;
		if ( $user_name == '' ) return $this->setErrorMessage ( "Cannot determine user name for batch #" . $batch_id ) ;
		if ( $this->isUserBlocked ( $user_name ) ) {
			$sql = "UPDATE batch SET status='BLOCKED' WHERE id=$batch_id" ;
			$db->query($sql) ;
			return $this->setErrorMessage ( "User:$user_name is blocked on Wikidata" ) ;
		}

		$sql = "UPDATE batch SET status='RUN_SEQUENTIAL' WHERE id=$batch_id" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return true ;
	}

	public function runNextCommandInBatchFast ( $batch_id, $row_num=-1 ) {

		// echo 'in run func';
		$db = $this->getDB();
		// var_dump($db);
		// echo $batch_id;

		$sql = "SELECT batch.last_item,batch.status,user.id AS user_id,user.name AS user_name FROM batch,user WHERE batch.id=$batch_id AND user.id=batch.user" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
// die;

		// echo 'after ';
		$o = $result->fetch_object() ;
		// echo 'this is o:';
		// var_dump($o);
		if($o->status == 'STOP'){
			die;
		}
		$this->last_item = $o->last_item ;
		$this->user_id = $o->user_id ;
		$this->user_name = $o->user_name ;
		$ts = $this->getCurrentTimestamp() ;

		$sql = "SELECT * FROM command_{$batch_id} WHERE batch_id=$batch_id AND status IN ('INIT') AND row_num=$row_num ORDER BY num LIMIT 1" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result->fetch_object() ;
		if ( $o == NULL ) { // Nothing more to do
			return false ;
			$sql = "UPDATE batch SET status='DONE',last_item='',message='',ts_last_change='$ts' WHERE id=$batch_id" ;
			if(!$result = $db->query($sql)){
				echo $db->error;
				return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
			}
		}

		// load OAuth, if available
		if ( $this->use_user_oauth_for_batch_edits ) {
			$sql = "SELECT serialized FROM batch_oauth WHERE batch_id=$batch_id" ;
			if($result = $db->query($sql)) {
				$oauth = $result->fetch_object() ;
				if ( $oauth !== NULL ) {
					$oa = unserialize($oauth->serialized) ;
					if ( $oa === false ) {
						$this->log( "Could not unserialize OAuth information for batch $batch_id:\n".$oauth->serialized );
						$this->use_oauth = false ;
					} else {
						$this->setOA( $oa ) ;
					}
				} else {
					// no OAuth information for this batch – perfectly normal for older batches, don’t log
					$this->use_oauth = false ;
				}
			} else {
				$this->log( "Could not load OAuth information for batch $batch_id [" . $db->error . ']' );
				$this->use_oauth = false ;
			}
		}

		// Update status
		$sql = "UPDATE command_{$batch_id} SET status='RUN',ts_change='$ts',message='' WHERE batch_id=$batch_id AND num={$o->num}" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}

		// Run command
		$summary = "[[:toollabs:quickstatements/#/batch/{$batch_id}|batch #{$batch_id}]] by [[User:{$this->user_name}|]]" ;
		$cmd = json_decode ( $o->json ) ;
		if ( !isset($cmd->summary) ) $cmd->summary = $summary ;
		else $cmd->summary .= '; ' . $summary ;
		// $this->use_oauth = false ;
		$this->runSingleCommand ( $cmd ) ;

		// Update batch status
		$db = $this->getDB() ;
		$ts = $this->getCurrentTimestamp() ;
		$sql = "UPDATE batch SET status='RUN',ts_last_change='$ts',last_item='{$this->last_item}' WHERE id=$batch_id AND status IN ('INIT','RUN')" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}

		// Update command status
		$status = strtoupper ( $cmd->status ) ;
		$msg = isset($cmd->message) ? $cmd->message : '' ;
		$message = '';
		if( $msg != '' ){
			$message = ",message='".$msg."'";
		}
		// echo "command message:";
		// var_dump($msg);
		$sql = "UPDATE command_{$batch_id} SET status='$status',ts_change='$ts',message='".$db->real_escape_string($msg)."' WHERE batch_id=$batch_id AND num={$o->num}" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		// Batch done?
		$sql = "SELECT count(*) AS cnt FROM command_{$batch_id} WHERE batch_id=$batch_id AND status NOT IN ('ERROR','DONE')" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result->fetch_object() ;
		if ( $o->cnt == 0 ) {
			$sql = "UPDATE batch SET status='DONE',last_item=''".$message." WHERE id=$batch_id" ;
			if(!$result = $db->query($sql)){
				echo $db->error;
				return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
			}
			return false;
		}

		return true ;
	}


	public function runNextCommandInBatchSequential ( $batch_id ) {
		// echo 'in run func';
		$db = $this->getDB() ;
		// var_dump($db);
		// echo $batch_id;

		$sql = "SELECT batch.last_item,user.id AS user_id,user.name AS user_name FROM batch,user WHERE batch.id=$batch_id AND user.id=batch.user" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}



		// echo 'after ';
		$o = $result->fetch_object() ;

		$this->last_item = $o->last_item ;
		$this->user_id = $o->user_id ;
		$this->user_name = $o->user_name ;
		$ts = $this->getCurrentTimestamp() ;

		$sql = "SELECT * FROM command_{$batch_id} WHERE batch_id=$batch_id AND status IN ('INIT') ORDER BY num LIMIT 1" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result->fetch_object() ;

		if ( $o == NULL ) { // Nothing more to do
			$sql = "UPDATE batch SET status='DONE',last_item='',message='',ts_last_change='$ts' WHERE id=$batch_id" ;
			if(!$result = $db->query($sql)){
				echo $db->error;
				return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
			}
			return true ;
		}

		// load OAuth, if available
		if ( $this->use_user_oauth_for_batch_edits ) {
			$sql = "SELECT serialized FROM batch_oauth WHERE batch_id=$batch_id" ;
			if($result = $db->query($sql)) {
				$oauth = $result->fetch_object() ;
				if ( $oauth !== NULL ) {
					//var_dump($oauth->serialized);
					//die;
					$oa = unserialize($oauth->serialized) ;
					if ( $oa === false ) {
						$this->log( "Could not unserialize OAuth information for batch $batch_id:\n".$oauth->serialized );
						$this->use_oauth = false ;
					} else {
						$this->setOA( $oa ) ;
					}
				} else {
					// no OAuth information for this batch – perfectly normal for older batches, don’t log
					$this->use_oauth = false ;
				}
			} else {
				$this->log( "Could not load OAuth information for batch $batch_id [" . $db->error . ']' );
				$this->use_oauth = false ;
			}
		}



		// Update status

		$sql = "UPDATE command_{$batch_id} SET status='RUN',ts_change='$ts',message='' WHERE batch_id=$batch_id AND num={$o->num}";
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}

		// Run command
		$summary = "[[:toollabs:quickstatements/#/batch/{$batch_id}|batch #{$batch_id}]] by [[User:{$this->user_name}|]]" ;
		$cmd = json_decode ( $o->json ) ;
		if ( !isset($cmd->summary) ) $cmd->summary = $summary ;
		else $cmd->summary .= '; ' . $summary ;
		// $this->use_oauth = false;
		$this->runSingleCommand ( $cmd ) ;

		// Update batch status
		$db = $this->getDB() ;
		$ts = $this->getCurrentTimestamp() ;
		$sql = "UPDATE batch SET status='RUN',ts_last_change='$ts',last_item='{$this->last_item}' WHERE id=$batch_id AND status IN ('INIT','RUN')" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}

		// Update command status
		$status = strtoupper ( $cmd->status ) ;
		$msg = isset($cmd->message) ? $cmd->message : '' ;
		$message = '';
		if( $msg != '' ){
			$message = ",message='".$msg."'";
		}
		// echo "command message:";
		// var_dump($msg);
		$sql = "UPDATE command_{$batch_id} SET status='$status',ts_change='$ts',message='".$db->real_escape_string($msg)."' WHERE batch_id=$batch_id AND num={$o->num}";
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		// Batch done?
		$sql = "SELECT count(*) AS cnt FROM command_{$batch_id} WHERE batch_id=$batch_id AND status NOT IN ('ERROR','DONE')" ;
		if(!$result = $db->query($sql)){
			echo $db->error;
			return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		}
		$o = $result->fetch_object() ;
		if ( $o->cnt == 0 ) {
			$sql = "UPDATE batch SET status='DONE',last_item=''".$message." WHERE id=$batch_id" ;
			if(!$result = $db->query($sql)){
				echo $db->error;
				return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
			}
		}

		return true ;

	}


	public function getToken ( $user_name ) {
		$db = $this->getDB() ;
		$token = '' ;
		$sql = "SELECT * FROM `user` WHERE name='" . $db->real_escape_string($user_name) . "'" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) {
			if ( $o->api_hash == '' ) continue ;
			return $o->api_hash ;
		}
		return $token ;
	}

	public function getUserIDfromNameAndToken ( $user_name , $token ) {
		if ( !isset($token) or $token == '' ) return ;

		$db = $this->getDB() ;
		$user_name = trim ( preg_replace ( '/_/' , ' ' , $user_name ) ) ;
		$sql = "SELECT * FROM `user` WHERE name='" . $db->real_escape_string($user_name) . "' AND api_hash='" . $db->real_escape_string($token) . "'" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) return $o->id*1 ;
	}

	public function generateToken ( $user_name , $force_replace = false ) {
		$user_name = trim ( str_replace ( '_' , ' ' , $user_name ) ) ;

		// Check existing token
		if ( !$force_replace ) {
			$token = $this->getToken ( $user_name ) ;
			if ( $token != '' ) return $token ;
		}

		// None to use, generate new one
		$db = $this->getDB() ;
		$token = password_hash ( rand() . $user_name . rand() . rand() , PASSWORD_DEFAULT ) ;
		$token = substr ( $token , 0 , 60 ) ;

		$id = '' ;
		$un = $db->real_escape_string($user_name) ;
		$sql = "SELECT * FROM user WHERE name='$un'" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) $id = $o->id ;

		if ( $id == '' ) {
			$sql = "INSERT INTO `user` (name,api_hash) VALUES ('$un','$token')" ;
		} else {
			$sql = "UPDATE `user` set api_hash='$token' WHERE id=$id" ;
		}
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;



		return $token ;
	}

	// $batches : array of batch_id!
	public function getBatchStatus ( $batches ) {
		$ret = array() ;
		if ( count($batches) == 0 ) return $ret ;
		$db = $this->getDB() ;
		$bl = implode ( ',' , $batches ) ;

		$sql = "SELECT user.name AS user_name,batch.* FROM batch,user WHERE user.id=batch.user AND batch.id IN ($bl)" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) {
			$batch_id = $o->id ;
			$ret["$batch_id"] = array ( 'batch' => $o , 'commands' => array() ) ;
		}

		$sql = "SELECT batch_id,status,count(*) AS cnt FROM command_{$batch_id} WHERE batch_id IN ($bl) GROUP BY batch_id,status" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		while ( $o = $result->fetch_object() ) {
			$batch_id = $o->batch_id ;
			$status = $o->status ;
//			print "$batch_id / $status\n" ;
//			if ( !isset($ret->$batch_id->commands) ) $ret->$batch_id->commands = (object) array() ;
			$ret["$batch_id"]['commands'][$status] = $o->cnt ;
		}

		return $ret ;
	}

	public function userChangeBatchStatus ( $batch_id , $new_status ) {
		if ( !$this->canCurrentUserChangeBatchStatus ( $batch_id ) ) return false ;

		$db = $this->getDB() ;
		if ( $new_status != 'STOP' ) { // Always allow user to stop a batch
			$user_name = $this->getUsernameFromBatchID ( $batch_id ) ;
			if ( $user_name == '' ) return $this->setErrorMessage ( "Cannot determine user name for batch #" . $batch_id ) ;
			if ( $this->isUserBlocked ( $user_name ) ) {
				$sql = "UPDATE batch SET status='BLOCKED' WHERE id=$batch_id" ;
				$db->query($sql) ;
				return $this->setErrorMessage ( "User:$user_name is blocked on Wikidata" ) ;
			}
		}

		$sql = "UPDATE batch SET status='" . $db->real_escape_string($new_status) . "',message='Status set by User:" . $db->real_escape_string($this->user_name) . "' WHERE id=$batch_id" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return true ;
	}




	protected function log ( $data ) {
		if ( !$this->logging ) return ;
		$out = $this->getCurrentTimestamp() . "\t" . json_encode ( $data ) . "\n" ;
		file_put_contents ( $this->config->logfile , $out , FILE_APPEND ) ;
	}

	protected function canCurrentUserChangeBatchStatus ( $batch_id ) {
		if ( false === $this->getCurrentUserID() ) return $this->setErrorMessage ( "Not logged in" ) ;

		$db = $this->getDB() ;
		$sql = "SELECT * FROM batch WHERE id=$batch_id" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		$batch = $result->fetch_object() ;

//		if ( !isset($this->user_id) ) $this->user_id = $this->getCurrentUserID() ;
		if ( $batch->user == $this->user_id ) return true ; // User who submitted the batch

		foreach ( $this->user_groups AS $k => $v ) {
			if ( $v == 'administrator' ) return true ;
		}

		return false ;
	}

	protected function getCurrentTimestamp () {
		return date ( 'YmdHis' ) ;
	}

	protected function setErrorMessage ( $msg ) {
		$this->last_error_message = $msg ;
		return false ;
	}

	protected function ensureCurrentUserInDB () {
		$db = $this->getDB() ;
		$sql = "INSERT IGNORE INTO user (id,name) VALUES ({$this->user_id},'".$db->real_escape_string($this->user_name)."')" ;
		if(!$result = $db->query($sql)) return $this->setErrorMessage ( 'There was an error running the query [' . $db->error . ']'."\n$sql" ) ;
		return true ;
	}

	protected function mergeItems ( $command ) {
		$this->runAction ( [
			'action' => 'wbmergeitems' ,
			'fromid' => $command->item1 ,
			'toid' => $command->item2 ,
			'ignoreconflicts' => 'description' ,
			'summary' => ''
		] , $command ) ;
		if ( $command->status != 'done' ) return $command ;
		if ( !$this->isBatchRun() ) {
			$this->wd->updateItem ( $command->item1 ) ;
			$this->wd->updateItem ( $command->item2 ) ;
		}
		return $command ;
	}

	protected function createNewItem ( $command ) {
		$data = '{}' ;
		if ( isset($command->data) ) $data = json_encode ( $this->array2object ( $command->data ) ) ;
		$this->runAction ( array (
			'action' => 'wbeditentity' ,
			'new' => $command->type ,
			'data' => $data ,
			'summary' => ''
		) , $command ) ;
		if ( $command->status != 'done' ) {
			$this->last_item = '' ; // Ensure subsequent commands will fail
			return $command ;
		}
		$this->last_item = $command->item ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function isProperty ( $p ) {
		if ( !isset($p) ) return false ;
		if ( !preg_match ( '/^[P]\d+$/i' , $p ) ) return false ;
		return true ;
	}

	protected function getStatementID ( $command ) {
		if ( !$this->isProperty ( $command->property ) ) return ;
		if ( !isset($command->datavalue) ) return ;
		$q = $command->item ;

		$this->wd->loadItem ( $q ) ;
		if ( !$this->wd->hasItem($q) ) return ;
		$i = $this->wd->getItem ( $q ) ;
		$claims = $i->getClaims ( $command->property ) ;
		foreach ( $claims AS $c ) {
			if ( !isset($c->mainsnak) or !isset($c->mainsnak->datavalue) ) continue ;
			if ( !isset($command->datavalue) ) continue ;
			if ( $this->compareDatavalue ( $c->mainsnak->datavalue , $command->datavalue ) ) return $c->id ;
		}
	}

	// Return true if both datavalues are the same (for any given value of same...), or false otherwise
	protected function compareDatavalue ( $d1 , $d2 ) {
		if ( $d1->type != $d2->type ) return false ;
		if ( $d1->type == 'string' ) {
			$value1 = normalizer_normalize($d1->value,Normalizer::FORM_D);
			$value2 = normalizer_normalize($d2->value,Normalizer::FORM_D);
			return $value1 == $value2;
		}
		if ( $d1->type == 'quantity' ) return $d1->value->amount*1 == $d2->value->amount*1 ;
		if ( $d1->type == 'time' ) {
			if ( $d1->value->calendarmodel != $d2->value->calendarmodel ) return false ;
			if ( $d1->value->precision != $d2->value->precision) return false ;
			# The Leading Zeroes Dance
			$t1 = preg_replace('/^([+-]{0,1})0*(.+)$/','$1$2',$d1->value->time) ;
			$t2 = preg_replace('/^([+-]{0,1})0*(.+)$/','$1$2',$d2->value->time) ;
			if ( $t1 != $t2 ) return false ;
			return true ;
		}
		if ( $d1->type == 'globecoordinate' ) return $d1->value->latitude == $d2->value->latitude and $d1->value->longitude == $d2->value->longitude and $d1->value->globe == $d2->value->globe ;
		if ( $d1->type == 'monolingualtext' ) return $d1->value->text == $d2->value->text and $d1->value->language == $d2->value->language ;

		$et = 'entity-type' ;
		$nid = 'numeric-id' ;
		if ( $d1->type == 'wikibase-entityid' ) {
			if ( $d1->value->$et != $d2->value->$et ) return false ;
			if ( isset($d1->value->$nid) and isset($d2->value->$nid) AND $d1->value->$nid==$d2->value->$nid ) return true ;
			if ( isset($d1->value->id) and isset($d2->value->id) AND $d1->value->id==$d2->value->id ) return true ;
			return false ;
		}

		return false ; // Can't determine type
	}

	public function compressCommands ( $commands ) {
		if ( !$this->use_command_compression ) return $commands ;
		if ( count($commands) < 2 ) return $commands ; // Nothing to do

		$out = array ( $commands[0] ) ;
		for ( $pos = 1 ; $pos < count($commands) ; $pos++ ) {
			if ( $commands[$pos] == 'SKIP' ) continue ;
			if ( $out[count($out)-1]['action'] == 'create' and $out[count($out)-1]['type'] == 'item' and $commands[$pos]['action'] == 'add'  and strtolower($commands[$pos]['item']) == 'last' ) {
				if ( !isset($out[count($out)-1]['data']) ) $out[count($out)-1]['data'] = array() ;
				if ( $commands[$pos]['what'] == 'label' ) {
					$lang = $commands[$pos]['language'] ;
					$out[count($out)-1]['data']['labels'][$lang] = array ( 'language' => $lang , 'value' => $commands[$pos]['value'] ) ;
				} else if ( $commands[$pos]['what'] == 'description' ) {
					$lang = $commands[$pos]['language'] ;
					$out[count($out)-1]['data']['descriptions'][$lang] = array ( 'language' => $lang , 'value' => $commands[$pos]['value'] ) ;
				} else if ( $commands[$pos]['what'] == 'alias' ) {
					$lang = $commands[$pos]['language'] ;
					$out[count($out)-1]['data']['aliases'][$lang][] = array ( 'language' => $lang , 'value' => $commands[$pos]['value'] ) ;
				} else if ( $commands[$pos]['what'] == 'sitelink' ) {
					$site = $commands[$pos]['site'] ;
					$out[count($out)-1]['data']['sitelinks'][$site] = array ( 'site' => $site , 'title' => $commands[$pos]['value'] ) ;

				} else if ( $commands[$pos]['what'] == 'statement' and isset($commands[$pos]['datavalue']) ) {

					$claim = array (
						'mainsnak' => array (
							'snaktype' => 'value' ,
							'property' => $commands[$pos]['property'] ,
							'datavalue' => $commands[$pos]['datavalue']
						) ,
						'type' => 'statement' ,
						'rank' => 'normal'
					) ;

					// BEGIN sources and qualifiers
					$pos2 = $pos+1 ;
					while ( $pos2 < count($commands)
						and $commands[$pos2]['action'] == 'add'
						and strtolower($commands[$pos2]['item']) == 'last'
						and in_array($commands[$pos2]['what'],array('qualifier','sources'))
						and $commands[$pos2]['property'] == $commands[$pos]['property']
						and isset ( $commands[$pos2]['datavalue'] )
						and serialize($commands[$pos2]['datavalue']) == serialize($claim['mainsnak']['datavalue'])
						) {

						if ( $commands[$pos2]['what'] == 'sources' ) {
							if ( !isset($claim['references']) ) $claim['references'] = array(  ) ;

							$refs = array('snaks'=>array()) ;
							foreach ( $commands[$pos2]['sources'] AS $s ) {
								$source = array (
									'snaktype' => 'value' ,
									'property' => $s['prop'] ,
									'datavalue' => $s['value']
								) ;
								$refs['snaks'][$s['prop']][] = $source ;
							}

							$claim['references'][] = $refs ;
						} else if ( $commands[$pos2]['what'] == 'qualifier' ) {
							$qual = array (
								'property' => $commands[$pos2]['qualifier']['prop'] ,
								'snaktype' => 'value' ,
								'datavalue' => $commands[$pos2]['qualifier']['value']
							) ;
							$claim['qualifiers'][] = $qual ;
						}

						$commands[$pos2] = 'SKIP' ;
						$pos2++ ;
					}
					// END sources and qualifiers

					$out[count($out)-1]['data']['claims'][] = $claim ;

				} else {
					$out[] = $commands[$pos] ;
				}
			} else {
				$out[] = $commands[$pos] ;
			}
		}

		return $out ;
	}

	protected function getSite () {
		$site = $this->config->site ;
		return $this->config->sites->$site ;
	}

	protected function getBotConfig () {
		if ( $this->bot_config_file != '' ) return parse_ini_file ( $this->bot_config_file ) ;
		return parse_ini_file ( $this->config->bot_config_file ) ;
	}

	protected function getBotAPI ( $force_login = false ) {
		global $qs_global_bot_api ;
		if ( !isset($this->bot_api) and isset($qs_global_bot_api) ) $this->bot_api = $qs_global_bot_api ;
		if ( !$force_login and isset($this->bot_api) and $this->bot_api->isLoggedIn() ) return $this->bot_api ;

		$bot_config = $this->getBotConfig() ;
		$api = new \Mediawiki\Api\MediawikiApi( $this->getAPI() );
		if ( $force_login or !$api->isLoggedin() ) {
			if ( isset($bot_config['user']) ) $username = $bot_config['user'] ;
			if ( isset($bot_config['username']) ) $username = $bot_config['username'] ;
			if ( isset($bot_config['pass']) ) $password = $bot_config['pass'] ;
			if ( isset($bot_config['password']) ) $password = $bot_config['password'] ;
			if ( !isset($username) or !isset($password) ) return false ;
			$x = $api->login( new \Mediawiki\Api\ApiUser( $username, $password ) );
			if ( !$x ) return false ;
		}
		if ( !isset($qs_global_bot_api) ) $qs_global_bot_api = $api ;
		$this->bot_api = $api ;
		return $api ;

	}

	protected function runBotAction ( $params_orig , $attempts_left = 3 ) {
		if ( $attempts_left <= 0 ) return false ;
		$params = array() ;
		foreach ( $params_orig AS $k => $v ) $params[$k] = $v ; // Copy to array, and for safekeeping original
		$this->last_result = (object) array() ;
		if ( !isset($params['action']) ) return false ;
		$action = $params['action'] ;
		unset ( $params['action'] ) ;

		$params['bot'] = 1 ;

		$api = $this->getBotAPI() ;
		$params['token'] = $api->getToken() ;

		try {
			$x = $api->postRequest( new \Mediawiki\Api\SimpleRequest( $action, $params ) );
			if ( isset($x) ) {
				$this->last_result = json_decode ( json_encode ( $x ) ) ; // Casting to object
			} else {
//				return false ; // TODO is that correct?
			}
		} catch (Exception $e) {
			$msg = $e->getMessage() ;
			if ( $msg == 'The save has failed.' ) {
				sleep ( 2 ) ;
				return $this->runBotAction ( $params_orig , $attempts_left-1 ) ;
			}
			$this->last_result->error = (object) array ( 'info' => $msg ) ;
			return false ;
		}
		return true ;
	}

	protected function runAction ( $params , &$command ) {
		$params = (object) $params ;
		$summary = '#quickstatements' ;
		if ( isset($params->summary) and $params->summary != '' ) $summary .= '; ' . $params->summary ;
		else if ( isset($command->summary) and $command->summary != '' ) $summary .= "; " . $command->summary ;
		if ( $this->toolname != '' ) $summary .= "; invoked by " . $this->toolname ;
		$params->summary = $summary ;
		$params->bot = 1 ;

		$result = (object) array() ;
		$status = false ;
		if ( $this->use_oauth ) {
			$oa = $this->getOA() ;
			$status = $oa->genericAction ( $params ) ;
			$this->log ( array ( $params , $status ) ) ;
			if ( isset($oa->last_res) ) $result = $oa->last_res ;
		} else {
			$status = $this->runBotAction ( $params ) ;
			if ( isset($this->last_result) ) $result = $this->last_result ;
		}

		$command->run = $params ; // DEBUGGING INFO
		if ( $status ) {
			$command->status = 'done' ;
			if ( $params->action == 'wbeditentity' and isset($params->new) ) {
				$command->item = $result->entity->id ; // "Last item"
			}
		} else {
			$command->status = 'error' ;
			if (
				(!isset($result) or $result === null or $result == '') ||
			 	( isset($result->error) and isset($result->error->info) )
			) {
				//todo- log the message here too. Idk why it isn't firing again.

				//if ( $this->retry_on_database_lock and $result->error->info == 'The database has been automatically locked while the slave database servers catch up to the master' ) {
				if (
					(!isset($result) or $result === null or $result == '') ||
					strpos($result->error->info,'Caught exception of type Wikimedia\Rdbms\DBQueryError') !== false ||
					strpos($result->error->info,'The authorization headers in your request are not valid') !== false
				) {
					// $msg = 'yay hardcoded quickstatemnet.php erorr';
					// if( isset($result->error->code)){
					// 	$msg = "quickstatement.php: ". json_encode($result->error->code);
					// }
					// if( isset($result->error->info)){
					// 	$msg = json_encode($result->error->info);
					// 	$msg = "quickstatement.php: ". $msg;
					// }
					// $servername = "localhost";
			        // $username = "josh";
			        // $password = "joshPassw0rd!";
			        // $dbname = "quickstatement_josh";
			        // $conn = new mysqli($servername, $username, $password, $dbname);
			        // if ($conn->connect_error) {
			        //     die("Connection failed: " . $conn->connect_error);
			        // }
			        // $sql = "INSERT INTO test (msg)
			        //      VALUES ('$msg')";
					//
			        // if ($conn->query($sql) === TRUE) {
			        //     //echo "New record created successfully";
			        // } else {
			        //     //echo "Error: " . $sql . "<br>" . $conn->error;
			        // }
			        // $conn->close();


					$command->status = '' ;
					sleep ( 3 ) ;
					$this->runAction ( $params , $command ) ;
					return ;
				}
				$command->message = $result->error->info ;
				if ( preg_match ( '/Invalid CSRF token/' , $result->error->info ) ) {
					exit ( 1 ) ; // Force bot restart
					if ( isset ( $qs_global_bot_api ) ) unset ( $qs_global_bot_api ) ;
					if ( isset ( $this->bot_api ) ) unset ( $this->bot_api ) ;
					$this->getBotAPI ( true ) ;
				}
			} else $command->message = json_encode ( $result ) ;
		}
	}

	protected function getDatatypeForProperty ( $property ) {
		$this->wd->loadItem ( $property ) ;
		if ( !$this->wd->hasItem($property) ) return ;
		$i = $this->wd->getItem ( $property ) ;
		return $i->j->datatype ;
	}

	protected function commandError ( $command , $message ) {
		$command->status = 'error' ;
		if ( isset($message) and $message != '' ) $command->message = $message ;
		return $command ;
	}

	protected function commandDone ( $command , $message ) {
		$command->status = 'done' ;
		if ( isset($message) and $message != '' ) $command->message = $message ;
		return $command ;
	}

	protected function getSnakType ( $datavalue ) {
		if ( $datavalue->value == 'novalue' || $datavalue->value == 'somevalue' ) {
			return $datavalue->value ;
		}
		return 'value' ;
	}

	protected function getPrefixedID ( $q ) {
		$q = trim ( strtoupper ( $q ) ) ;
		// TODO generic
		if ( preg_match ( '/^P\d+$/' , $q ) ) return "Property:$q" ;
		return $q ;
	}

	protected function commandAddStatement ( $command , $i , $statement_id ) {
		// Paranoia
		if ( isset($statement_id) ) return $this->commandDone ( $command , "Statement already exists as $statement_id" ) ;

		// Execute!
		$action = array (
			'action' => 'wbcreateclaim' ,
			'entity' => $command->item ,
			'snaktype' => $this->getSnakType ( $command->datavalue ) ,
			'property' => $command->property ,
			'value' => json_encode ( $command->datavalue->value ) ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) ;
		if ( $action['snaktype'] != 'value' ) unset( $action['value'] );
		$this->runAction ( $action , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandAddQualifier ( $command , $i , $statement_id ) {
		// Paranoia
		if ( !isset($command->qualifier) ) return $this->commandError ( $command , "Incomplete command parameters" ) ;
		if ( !isset($command->qualifier->prop) ) return $this->commandError ( $command , "Incomplete command parameters" ) ;
		if ( !preg_match ( '/^P\d+$/' , $command->qualifier->prop ) ) return $this->commandError ( $command , "Invalid qualifier property {$command->qualifier->prop}" ) ;

		// Execute!
		$action = array (
			'action' => 'wbsetqualifier' ,
			'claim' => $statement_id ,
			'property' => $command->qualifier->prop ,
			'value' => json_encode ( $command->qualifier->value->value ) ,
			'snaktype' => $this->getSnakType ( $command->qualifier->value ) ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) ;
		if ( $action['snaktype'] != 'value' ) unset( $action['value'] );
		$this->runAction ( $action , $command ) ;
		if ( $command->status == 'error' and preg_match ( '/The statement has already a qualifier/' , $command->message ) ) $command->status = 'done' ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandAddSources ( $command , $i , $statement_id ) {
		// Paranoia
		if ( !isset($command->sources) ) return $this->commandError ( $command , "Incomplete command parameters" ) ;
		if ( count($command->sources) == 0 ) return $this->commandError ( $command , "No sources to add" ) ;

		// Prep
		$snaks = array() ;
		foreach ( $command->sources AS $source ) {
			$s = array(
				'snaktype' => $this->getSnakType ( $source->value ) ,
				'property' => $source->prop ,
				'datavalue' => $source->value
			) ;
			if ( $s['snaktype'] != 'value' ) unset( $s['datavalue'] ) ;
			$snaks[$source->prop][] = $s ;
		}

		// Execute!
		$this->runAction ( array (
			'action' => 'wbsetreference' ,
			'statement' => $statement_id ,
			'snaks' => json_encode ( $snaks ) ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandSetLabel ( $command , $i ) {
		// Paranoia
		if ( $i->getLabel ( $command->language , true ) == $command->value ) return $this->commandDone ( $command , 'Already has that label for {$command->language}' ) ;

		// Execute!
		$this->runAction ( array (
			'action' => 'wbsetlabel' ,
			'id' => $this->getPrefixedID ( $command->item ) ,
			'language' => $command->language ,
			'value' => $command->value ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandAddAlias ( $command , $i ) {
		// Paranoia TODO

		// Execute!
		$this->runAction ( array (
			'action' => 'wbsetaliases' ,
			'id' => $this->getPrefixedID ( $command->item ) ,
			'language' => $command->language ,
			'add' => $command->value ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandSetDescription ( $command , $i ) {
		// Paranoia
		if ( $i->getDesc ( $command->language , true ) == $command->value ) return $this->commandDone ( $command , 'Already has that description for {$command->language}' ) ;

		// Execute!
		$this->runAction ( array (
			'action' => 'wbsetdescription' ,
			'id' => $this->getPrefixedID ( $command->item ) ,
			'language' => $command->language ,
			'value' => $command->value ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandSetSitelink ( $command , $i ) {
		// Paranoia
		$sl = $i->getSitelink ( $command->site ) ;
		if ( isset($sl) and str_replace(' ','_',$sl) == str_replace(' ','_',$command->value) ) return $this->commandDone ( $command , 'Already has that sitelink for {$command->site}' ) ;

		// Execute!
		$this->runAction ( array (
			'action' => 'wbsetsitelink' ,
			'id' => $this->getPrefixedID ( $command->item ) ,
			'linksite' => $command->site ,
			'linktitle' => $command->value ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandRemoveStatement ( $command ) {
		$id = $command->id ;
		$q = strtoupper ( preg_replace ( '/\$.*$/' , '' , $id ) ) ;
		$i = $this->wd->getItem ( $q ) ;

		// Execute!
		$this->runAction ( array (
			'action' => 'wbremoveclaims' ,
			'claim' => $id ,
			'summary' => '' ,
			'baserevid' => $i->j->lastrevid
		) , $command ) ;
		if ( !$this->isBatchRun() ) $this->wd->updateItem ( $command->item ) ;
		return $command ;
	}

	protected function commandRemoveSitelink ( $command, $i ) {
		$command->value = '';
		return $this->commandSetSitelink ( $command, $i );
	}

	public function array2object_recursive($array) {
		$obj = new stdClass;
		foreach($array as $k => $v) {
			if(strlen($k)) {
				if(is_array($v)) {
					$obj->{$k} = $this->array2object_recursive($v); //RECURSION
				} else {
					$obj->{$k} = $v;
				}
				}
		}
		return $obj;
	}

	public function array2object ( $a ) {
		$ret = json_decode ( json_encode ( $a ) ) ;
		if ( isset($ret) ) return $ret ;
		return $this->array2object_recursive ( $a ) ;
	}

	public function runCommandArray ( $commands ) {
		if ( $this->use_command_compression ) $commands = $this->compressCommands ( $commands ) ;
		$this->is_batch_run = true ;
		foreach ( $commands AS $command_original ) {
			$command = $this->array2object ( $command_original ) ;
			$command = $this->runSingleCommand ( $command ) ;
			if ( $command->status != 'done' ) {
				print "<pre>" ; print_r ( $command ) ; print "</pre>" ;
			}
			// TODO proper error handling
			if ( isset($command->item) ) $this->wd->updateItem ( $command->item ) ;
		}
		$this->is_batch_run = false ;
	}

	public function runSingleCommand ( $command ) {
		if ( $this->sleep != 0 ) sleep ( $this->sleep ) ;
		if ( !isset($command) ) return $this->commandError ( $command , "Empty command" ) ;
		$command->status = 'working' ;
		if ( isset($command->error) ) unset ( $command->error ) ;
		if ( isset($this->temporary_batch_id) ) {
			if ( isset($command->summary) ) $command->summary .= ' ' . $this->temporary_batch_id ;
			else $command->summary = $this->temporary_batch_id ;
		}

		if ( $command->action == 'create' ) {
			return $this->createNewItem ( $command ) ;
		} else if ( $command->action == 'merge' ) {
			return $this->mergeItems ( $command ) ;
		} else {

			// Prepare
			if ( !isset($command->item) and isset($command->id) and $command->what == 'statement' ) $command->item = strtoupper ( preg_replace ( '/\$.+$/' , '' , $command->id ) ) ;
			$q = trim($command->item) ;
			if ( strtolower($q) == 'last' ) $q = $this->last_item ;
			if ( $q == '' ) return $this->commandError ( $command , 'No last item available' ) ;
			$command->item = $q ;
			$to_load = array ( $q ) ;
			if ( isset($command->property) and $this->isProperty($command->property) ) $to_load[] = $command->property ;
			$this->wd->loadItems ( $to_load ) ;
			if ( !$this->wd->hasItem($q) ) return $this->commandError ( $command , "Item $q is not available" ) ;
			$i = $this->wd->getItem ( $q ) ;

			if ( $command->action == 'add' ) {

				// Do it
				if ( $command->what == 'label' ) return $this->commandSetLabel ( $command , $i ) ;
				if ( $command->what == 'alias' ) return $this->commandAddAlias ( $command , $i ) ;
				if ( $command->what == 'description' ) return $this->commandSetDescription ( $command , $i ) ;
				if ( $command->what == 'sitelink' ) return $this->commandSetSitelink ( $command , $i ) ;

				if ( ! $this->propagateLastItem ( $command ) ) {
					return $this->commandError( $command, "No last item available" );
				}

				$statement_id = $this->getStatementID ( $command ) ;
				if ( $command->what == 'statement' ) return $this->commandAddStatement ( $command , $i , $statement_id ) ;

				// THE FOLLOWING DEPEND ON AN EXISTING STATEMENT
				if ( !isset($statement_id) ) return $this->commandError ( $command , "Base statement not found" ) ;
				if ( $command->what == 'qualifier' ) return $this->commandAddQualifier ( $command , $i , $statement_id ) ;
				if ( $command->what == 'sources' ) return $this->commandAddSources ( $command , $i , $statement_id ) ;

			} else if ( $command->action == 'remove' ) {

				if ( $command->what == 'statement' ) {
					if ( !isset($command->id) ) $command->id = $this->getStatementID ( $command ) ;
					if ( !isset($command->id) or $command->id == '' ) return $this->commandError ( $command , "Base statement not found" ) ;
					return $this->commandRemoveStatement ( $command ) ;
				} else if ( $command->what == 'sitelink' ) {
					return $this->commandRemoveSitelink ( $command, $i ) ;
				}

			}

		}
		return $this->commandError( $command, "Incomplete or unknown command" );
	}


	protected function propagateLastItem ( $command ) {
		$values = [ $command->datavalue ];
		if ( isset($command->qualifier->value) ) {
			$values[] = $command->qualifier->value ;
		}
		if ( isset($command->sources) ) {
			foreach ( $command->sources as $source ) {
				$values[] = $source->value;
			}
		}

		foreach ( $values as $value ) {
			if ( isset ( $value->type ) && $value->type === 'wikibase-entityid' && strtoupper( $value->value->id ) === 'LAST') {
				if ( !$this->last_item ) {
					return false ;
				}
				$value->value->id = $this->last_item ;
			}
		}

		return true ;
	}


	protected function importDataFromV1 ( $data , &$ret ) {
		$ret['data']['commands'] = array() ;
		if ( strpos ( $data , "\n" ) === false ) $data = str_replace ( '||' , "\n" , $data ) ;
		if ( strpos ( $data , "\t" ) === false ) $data = str_replace ( '|' , "\t" , $data ) ;
		$rows = explode ( "\n" , $data ) ;
		//echo json_encode($rows);die;
		$rowNum = 0;
		$lastCreateRowNum = -1;
		$editedQids = array();

		foreach ( $rows as $row ) {
			$rowNum++;
			$row = trim ( $row ) ;
			$comment = '' ;
			if ( preg_match ( '/^(.*?) *\/\* *(.*?) *\*\/ *$/' , $row , $m ) ) { // Extract comment as summary
				$comment = $m[2] ;
				$row = $m[1] ;
			}
			$cols = explode ( "\t" , $row ) ;
			$cmd = array() ;
			$skip_add_command = false ;
			$action = 'add' ;
			if ( count($cols)>0 and preg_match('/^-(.+)$/',$cols[0],$m) ) {
				$action = 'remove' ;
				$cols[0] = $m[1] ;
			}
			if( isset($cols[1]) && substr( $cols[1], 0, 2 ) === 'DP' ){
				$cols[1] = ltrim($cols[1], 'D');
				$cols[2] = "CREATED-".$cols[2];
			}
			$first = strtoupper(trim($cols[0])) ;
			if ( count ( $cols ) >= 3 and ( $this->isValidItemIdentifier($first) or $first == 'LAST' ) and $this->isValidItemIdentifier($cols[1]) ) {

				$tmpRowNum = $rowNum;
				if( $first == 'LAST' ){
					$tmpRowNum = $lastCreateRowNum;
					$rowNum--;
				}else{
					if(!isset($editedQids[$first])){
						$editedQids[$first] = $rowNum;
					}else{
						$rowNum--;
					}
					$tmpRowNum = $editedQids[$first];
				}

				$prop = strtoupper(trim($cols[1])) ;
				$cmd = array ( 'action'=>$action , 'item'=>$first , 'property'=>$prop , 'what'=>'statement', 'rowNum' => $tmpRowNum ) ;
				if ( $comment != '' ) $cmd['summary'] = $comment ;
				$this->parseValueV1 ( $cols[2] , $cmd ) ;
				// Remove base statement
				array_shift ( $cols ) ;
				array_shift ( $cols ) ;
				array_shift ( $cols ) ;

				// Add qualifiers and sources
				while ( count($cols) >= 2 ) {
					$key = array_shift ( $cols ) ;
					$key = strtoupper ( trim ( $key ) ) ;
					$value = array_shift ( $cols ) ;
					if ( preg_match ( '/^([SP])(\d+)$/i' , $key , $m ) ) {
						$what = $m[1] == 'S' ? 'sources' : 'qualifier' ;
						$num = $m[2] ;

						$skip_add_command = true; //always skip base statement
						if ( !$skip_add_command ) $ret['data']['commands'][] = $cmd ;
						$skip_add_command = false ;
						//$last_command = $ret['data']['commands'][count($ret['data']['commands'])-1] ;

						$cmd = array ( 'action'=>$action , 'item'=>$first , 'property'=>$prop , 'what'=>$what , 'datavalue'=>$cmd['datavalue'], 'rowNum' => $tmpRowNum ) ;
						$dummy = array() ;
						$this->parseValueV1 ( $value , $dummy ) ; // TODO transfer error message
						$dv = array ( 'prop' => 'P'.$num , 'value' => $dummy['datavalue'] ) ;
						if ( $what == 'sources' ) $cmd[$what] = array($dv) ;
						else $cmd[$what] = $dv ;
//$ret['debug'][] = array ( $what , $last_command['what'] ) ;
						if ( $what == 'sources' and $last_command['what'] == $what ) {
							$ret['data']['commands'][count($ret['data']['commands'])-1][$what][] = $cmd[$what][0] ;
							$skip_add_command = true ;
//							$last_command[$what][] = $cmd[$what][0] ;
						}
					}
				}
				if ( count($cols) != 0 ) $cmd['error'] = 'Incomplete reference/qualifier list' ;
			} else if ( count ( $cols ) === 3 and ( $this->isValidItemIdentifier($first) or $first == 'LAST' ) and preg_match ( '/^([LADS])([a-z_-]+)$/i' , $cols[1] , $m ) ) {
				$code = strtoupper ( $m[1] ) ;
				$lang = strtolower ( trim ( $m[2] ) ) ;
				$tmpRowNum = $rowNum;
				if( $first == 'LAST' ){
					$tmpRowNum = $lastCreateRowNum;
					$rowNum--;
				}else{
					if(!isset($editedQids[$first])){
						$editedQids[$first] = $rowNum;
					}else{
						$rowNum--;
					}
					$tmpRowNum = $editedQids[$first];
				}
				$cmd = array ( 'action'=>$action , 'what'=>$this->actions_v1[$code] , 'item'=>$first, 'rowNum' => $tmpRowNum ) ;
				if ( $comment != '' ) $cmd['summary'] = $comment ;
				if ( $code == 'S' ) $cmd['site'] = $lang ;
				else $cmd['language'] = $lang ;
				$this->parseValueV1 ( $cols[2] , $cmd ) ;
				if ( !isset($cmd['datavalue']) or !isset($cmd['datavalue']['value']) ) {
					$cmd['error'] = 'Broken command' ;
					continue ;
				}
				$cmd['value'] = $cmd['datavalue']['value'] ;
				unset ( $cmd['datavalue'] ) ;
			} else if ( $first == 'MERGE' and count($cols) == 3 and $this->isValidItemIdentifier($cols[1]) and $this->isValidItemIdentifier($cols[2]) ) {
				$q1 = $cols[1] ;
				$q2 = $cols[2] ;
				if ( preg_replace('/\D/','',$q1)*1 < preg_replace('/\D/','',$q2)*1 ) list($q1,$q2) = [$q2,$q1] ; // Always merge into older item
				$cmd = array ( 'action'=>'merge' , 'type'=>'item' , 'item1' => $q1 , 'item2' => $q2, 'rowNum' => $rowNum ) ;
				if ( $comment != '' ) $cmd['summary'] = $comment ;
			} else if ( strpos($first, 'CREATE-') === 0 || strpos($first, 'CREATE') === 0 ){ //$first == 'CREATE' ) {
				if( strpos($first, 'CREATE-') === 0 ){
					$value22 = explode('CREATE-', $first)[1];
				}else{
					$value22 = '';
				}
				$lastCreateRowNum = $rowNum;
				$cmd = array ( 'action'=>'create' , 'type'=>'item', 'create-id'=>$value22, 'rowNum' => $rowNum) ;
				if ( $comment != '' ) $cmd['summary'] = $comment ;
			} else if ( $first == 'STATEMENT' and count($cols) == 2 ) {
				$id = trim ( $cols[1] ) ;
				$cmd = array ( 'action'=>$action , 'what'=>'statement' , 'id'=>$id, 'rowNum' => $rowNum ) ;
				if ( $comment != '' ) $cmd['summary'] = $comment ;
			}

			if ( isset($cmd['action']) && !$skip_add_command ) $ret['data']['commands'][] = $cmd ;
		}
//		if ( $this->use_command_compression ) $ret['data']['commands'] = $this->compressCommands ( $ret['data']['commands'] ) ;
	}

	protected function isValidItemIdentifier ( $q ) {
		return preg_match ( '/^(?:[PQL]\d+|L\d+-[FS]\d+)$/' , $q ) ;
	}

    protected function importDataFromCSV ( $data, &$ret ) {
        $commands = [];
        $ret['data']['commands'] = &$commands;

        // write the CSV string to a "file" so we can use fgetcsv which unlike str_getcsv supports multiple lines
        $stream = fopen( 'php://temp', 'r+' );
        fwrite( $stream, $data );
        rewind( $stream );

        $header = fgetcsv( $stream );
        if ( $header[0] !== 'qid' ) { // this is deliberately case-sensitive, so Qid, QID etc. are reserved for future expansion
            fclose( $stream );
            return; // TODO error message
        }
        array_shift( $header );
        if ( in_array( 'qid', $header ) ) {
            fclose( $stream );
            return; // TODO error message
        }
        if ( $header[0] === '#' ) {
            fclose( $stream );
            return; // TODO error message: there must be at least one real command before the first comment / summary
        }

        while ( ( $row = fgetcsv( $stream ) ) !== false ) {
            $qid = array_shift( $row );
            if ( $qid === '' ) {
                $commands[] = [ 'action' => 'create', 'type' => 'item' ];
                $qid = 'LAST';
            }
            $lastStatementProperty = null;
            $lastStatementDatavalue = null;
            unset( $lastSources ); // break reference
            $lastSources = null;

            foreach ( $row as $index => $value ) {
                $command = [
                    'action' => 'add',
                    'item' => $qid
                ];
                $instruction = $header[$index];

                if ( $instruction[0] === '-' ) {
                    $command['action'] = 'remove';
                    $instruction = substr( $instruction, 1 );
                }

                if ( $instruction[0] === 'P' || substr( $instruction, 0, 2 ) === 'DP' ) {
					if( ssubstr( $instruction, 0, 2 ) === 'DP' ){
						$value = "CREATE=".$value;
						$instruction = ltrim($instruction, 'D');
					}
                    $command += [
                        'what' => 'statement',
                        'property' => $instruction
                    ];
                    $this->parseValueV1( $value, $command );
                    $lastStatementProperty = $instruction;
                    $lastStatementDatavalue = $command['datavalue'];
                    unset( $lastSources ); // break reference
                    $lastSources = null;
                    $commands[] = $command;
                } elseif ( $instruction[0] === 'L' ) {
                    $command += [
                        'what' => 'label',
                        'language' => substr( $instruction, 1 ),
                        'value' => $value
                    ];
                    $commands[] = $command;
                } elseif ( $instruction[0] === 'D' ) {
                    $command += [
                        'what' => 'description',
                        'language' => substr( $instruction, 1 ),
                        'value' => $value
                    ];
                    $commands[] = $command;
                } elseif ( $instruction[0] === 'A' ) {
                    $command += [
                        'what' => 'alias',
                        'language' => substr( $instruction, 1 ),
                        'value' => $value
                    ];
                    $commands[] = $command;
                } elseif ( substr( $instruction, 0, 3 ) === 'qal' ) {
                    if ( $lastStatementProperty === null || $lastStatementDatavalue === null ) {
                        fclose( $stream );
                        return; // TODO error message
                    }
                    $dummy = []; // parseValueV1 writes to 'datavalue', but the qualifier needs 'value', so we parse into this dummy and copy the value later
                    $this->parseValueV1( $value, $dummy );
                    $command += [
                        'what' => 'qualifier',
                        'property' => $lastStatementProperty,
                        'datavalue' => $lastStatementDatavalue,
                        'qualifier' => [ 'prop' => 'P' . substr( $instruction, 3 ), 'value' => $dummy['datavalue'] ]
                    ];
                    $commands[] = $command;
                } elseif ( $instruction[0] === 'S' && ctype_digit( $instruction[1] ) ) {
                    if ( $lastStatementProperty === null || $lastStatementDatavalue === null ) {
                        fclose( $stream );
                        return; // TODO error message
                    }
                    $dummy = []; // parseValueV1 writes to 'datavalue', but the source needs 'value', so we parse into this dummy and copy the value later
                    $this->parseValueV1( $value, $dummy );
                    unset( $lastSources ); // break reference
                    $lastSources = [ [ 'prop' => 'P' . substr( $instruction, 1 ), 'value' => $dummy['datavalue'] ] ];
                    $command += [
                        'what' => 'sources',
                        'property' => $lastStatementProperty,
                        'datavalue' => $lastStatementDatavalue,
                        'sources' => &$lastSources
                    ];
                    $commands[] = $command;
                } elseif ( $instruction[0] === 's' && ctype_digit( $instruction[1] ) ) {
                    if ( $lastSources === null ) {
                        fclose( $stream );
                        return; // TODO error message
                    }
                    $dummy = []; // parseValueV1 writes to 'datavalue', but the source needs 'value', so we parse into this dummy and copy the value later
                    $this->parseValueV1( $value, $dummy );
                    $lastSources[] = [ 'prop' => 'P' . substr( $instruction, 1 ), 'value' => $dummy['datavalue'] ];
                } elseif ( $instruction[0] === 'S' ) {
                    $command += [
                        'what' => 'sitelink',
                        'site' => substr( $instruction, 1 ),
                        'value' => $value
                    ];
                    $commands[] = $command;
                } elseif ( $instruction === '#' ) {
                    $lastIndex = count( $commands ) - 1;
                    $commands[$lastIndex]['summary'] = $value;
                } else {
                    fclose( $stream );
                    return; // TODO error message
                }
            }
        }
        fclose( $stream );
    }


	protected function getEntityType ( $q ) {
		$q = strtoupper ( trim ( $q ) ) ;
		if ( preg_match ( '/^Q\d+$/' , $q ) ) return 'item' ;
		if ( preg_match ( '/^P\d+$/' , $q ) ) return 'property' ;
		if ( preg_match ( '/^L\d+$/' , $q ) ) return 'lexeme' ;
		if ( preg_match ( '/^L\d+-F\d+$/' , $q ) ) return 'form' ;
		if ( preg_match ( '/^L\d+-S\d+$/' , $q ) ) return 'sense' ;
		return 'unknown' ;
	}


	protected function convertToUTF8($text){
		$encoding = mb_detect_encoding($text, mb_detect_order(), false);
		if ( $encoding == "UTF-8" ) {
			$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
		}
		$ret = iconv(mb_detect_encoding($text, mb_detect_order(), false), "UTF-8//IGNORE", $text);
		return $ret;
	}

	protected function enforceStringEncoding ( $s ) {
		return $this->convertToUTF8($s) ;
#		return @iconv('UTF-8', 'UTF-8//IGNORE', $s) ;
	}

	protected function parseValueV1 ( $v , &$cmd ) {
		$v = trim ( $v ) ;

		if ( $v == 'somevalue' || $v == 'novalue' ) {
			$cmd['datavalue'] = array ( "value"=>$v, "type"=>$v ) ;
			return true ;
		}

		if ( $v == 'LAST' ) {
			$cmd['datavalue'] = array ( "type"=>"wikibase-entityid" , "value"=>array("entity-type"=>"item", "id"=>"LAST") ) ;
			return true ;
		}



		if ( preg_match ( '/^(?:[PQL]\d+|L\d+-[FS]\d+)$/i' , $v ) ) { // ENTITY TODO generic
			$cmd['datavalue'] = array ( "type"=>"wikibase-entityid" , "value"=>array("entity-type"=>$this->getEntityType($v),"id"=>strtoupper($v)) ) ;
			return true ;
		}

		if ( preg_match ( '/^"(.*)"$/i' , $v , $m ) ) { // STRING
			$cmd['datavalue'] = array ( "type"=>"string" , "value"=>trim($this->enforceStringEncoding($m[1])) ) ;
			return true ;
		}

		if ( preg_match ( '/^([a-z_-]+):"(.*)"$/i' , $v , $m ) ) { // MONOLINGUALTEXT
			$cmd['datavalue'] = array ( "type"=>"monolingualtext" , "value"=>array("language"=>$m[1],"text"=>trim($this->enforceStringEncoding($m[2]))) ) ;
			return true ;
		}

		if ( preg_match ( '/^([+-]{0,1})(\d+)-(\d\d)-(\d\d)T(\d\d):(\d\d):(\d\d)Z\/{0,1}(\d*)$/i' , $v , $m ) ) { // TIME
			$prec = 9 ;
			if ( $m[8] != '' ) $prec = $m[8]*1 ;
			$cmd['datavalue'] = array ( "type"=>"time" , "value"=>array(
				'time' => preg_replace ( '/\/\d+$/' , '' , $v ) ,
				'timezone' => 0 ,
				'before' => 0 ,
				'after' => 0 ,
				'precision' => $prec ,
				'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727'
			) ) ;
			return true ;
		}

		if ( preg_match ( '/^\@\s*([+-]{0,1}[0-9.]+)\s*\/\s*([+-]{0,1}[0-9.]+)$/i' , $v , $m ) ) { // GPS
			$cmd['datavalue'] = array ( "type"=>"globecoordinate" , "value"=>array(
				'latitude' => $m[1]*1 ,
				'longitude' => $m[2]*1 ,
				'precision' => 0.000001 ,
				'globe' => 'http://www.wikidata.org/entity/Q2'
			) ) ;
			return true ;
		}

		if ( preg_match ( '/^([\+\-]{0,1}\d+(\.\d+){0,1})(U(\d+)){0,1}$/' , $v , $m ) ) { // Quantity
			// echo json_encode(array('quantity match', $v, $m));
			// die;
			$cmd['datavalue'] = array ( "type"=>"quantity" , "value"=>array(
				"amount" => $m[1] ,
				"unit" => isset( $m[4] ) ? "http://www.wikidata.org/entity/Q{$m[4]}" : "1"
			) ) ;
			return true ;
		}
		if ( preg_match ( '/^([\+\-]{0,1}\d+(\.\d+){0,1})\s*~\s*([\+\-]{0,1}\d+(\.\d+){0,1})(U(\d+)){0,1}$/' , $v , $m ) ) { // Quantity with error
			$value = $m[1]*1 ;
			$error = $m[3]*1 ;
			$cmd['datavalue'] = array ( "type"=>"quantity" , "value"=>array(
				"amount" => $m[1] , // use $m[1] (string) instead of $value (float) to avoid precision problems
				"upperBound" => $value+$error ,
				"lowerBound" => $value-$error ,
				"unit" => isset( $m[6] ) ? "http://www.wikidata.org/entity/Q{$m[6]}" : "1"
			) ) ;
			return true ;
		}

		if ( strpos($v, 'CREATED-') == 0 ){
			$value = explode('CREATED-', $v)[1];
			$cmd['datavalue'] = array ( "type"=>"wikibase-entityid" , "value"=>array("entity-type"=>"created","id"=>$value) ) ;
			return true ;
		}


		$cmd['datavalue'] = array ( "type"=>"unknown" , "text"=>$v ) ;
		$cmd['error'] = array('PARSE','Unknown V1 value format') ;
	}

} ;

?>
