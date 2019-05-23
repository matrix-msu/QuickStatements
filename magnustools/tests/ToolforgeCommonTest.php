<?PHP

# Requires PHP7
# Run with ./vendor/bin/phpunit --bootstrap vendor/autoload.php ToolforgeCommonTest.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
use PHPUnit\Framework\TestCase;

require_once ( __DIR__ . '/../public_html/php/ToolforgeCommon.php' ) ;

/**
 * @covers ToolforgeCommon
 */
final class ToolforgeCommonTest extends TestCase {

	private $test_url = 'https://tools.wmflabs.org/magnustools/test.file' ;

	public function testCanConnectToLanguageProject() :void {
		$tfc = new ToolforgeCommon() ;
		$db = $tfc->openDB ( 'en' , 'wikipedia' ) ;
		$this->assertEquals ( $db->ping() , true ) ;
		$sql = "SELECT * FROM page WHERE page_namespace=0 AND page_title='Main_Page'" ;
		$result = $tfc->getSQL ( $db , $sql ) ;
		$o = $result->fetch_object() ;
		$this->assertEquals ( $o->page_id , 15580374 ) ;
	}

	public function testCanConnectToWiki() :void {
		$tfc = new ToolforgeCommon() ;
		$db = $tfc->openDBwiki ( 'enwiki' ) ;
		$this->assertEquals ( $db->ping() , true ) ;
		$sql = "SELECT * FROM page WHERE page_namespace=0 AND page_title='Main_Page'" ;
		$result = $tfc->getSQL ( $db , $sql ) ;
		$o = $result->fetch_object() ;
		$this->assertEquals ( $o->page_id , 15580374 ) ;
	}

	public function testCanConnectToTool() :void {
		$tfc = new ToolforgeCommon() ;
		$db = $tfc->openDBtool ( 'mixnmatch_p' , '' , 's51434' ) ;
		$this->assertEquals ( $db->ping() , true ) ;
		$sql = "SELECT * FROM catalog WHERE id=1" ;
		$result = $tfc->getSQL ( $db , $sql ) ;
		$o = $result->fetch_object() ;
		$this->assertEquals ( $o->name , 'ODNB' ) ;
	}
	
	public function testCanDoPostRequest() :void {
		$tfc = new ToolforgeCommon() ;
		$s = $tfc->doPostRequest ( $this->test_url , [] ) ;
		$s = trim ( $s ) ;
		$this->assertEquals ( $s , 'THIS IS A TEST!' ) ;
	}
	
	public function testCanDoParallelRequests() :void {
		$tfc = new ToolforgeCommon() ;
		$urls = [ 'testing' => $this->test_url ] ;
		$result = $tfc->getMultipleURLsInParallel ( $urls ) ;
		$s = trim ( $result['testing'] ) ;
		$this->assertEquals ( $s , 'THIS IS A TEST!' ) ;
	}
	
	public function testCanDoSPARQL() :void {
		$tfc = new ToolforgeCommon() ;
		$sparql = 'SELECT ?q { ?q wdt:P214 "113230702" }' ;
		$items = $tfc->getSPARQLitems ( $sparql ) ;
		$this->assertEquals ( count($items) , 1 ) ;
		$this->assertEquals ( $items[0] , 'Q42' ) ;
	}

	public function testCanGetRequest() :void {
		$_REQUEST['test_defined'] = 'abc' ;
		$tfc = new ToolforgeCommon() ;
		$result_with_default = $tfc->getRequest ( 'test_undefined' , 'default' ) ;
		$this->assertEquals ( $result_with_default , 'default' ) ;
		$result_no_default = $tfc->getRequest ( 'test_defined' , 'default' ) ;
		$this->assertEquals ( $result_no_default , 'abc' ) ;
	}
	
	public function testCanGetPagesInCategoryTree() :void {
		$tfc = new ToolforgeCommon() ;
		$db = $tfc->openDBwiki ( 'enwiki' ) ;
		$pages = $tfc->getPagesInCategory ( $db , 'Secularism in the United Kingdom' , 2 , 0 , true ) ;
		$this->assertArrayHasKey ( 'Richard_Dawkins' , $pages ) ;
	}

	public function testHasWikidataItemEverBeenEditedWithString() :void {
		$tfc = new ToolforgeCommon() ;
		$x = $tfc->hasWikidataItemEverBeenEditedWithString ( 'Q6431265' , 'mixnmatch:microsync for catalog 238' ) ;
		$this->assertEquals ( $x , true ) ;
		$x = $tfc->hasWikidataItemEverBeenEditedWithString ( 'Q6431265' , '79vsbvisvsvbhuseghkseghk7sgevbesesfksfsekfb' ) ; # Unlikely string
		$this->assertEquals ( $x , false ) ;
	}

}

?>