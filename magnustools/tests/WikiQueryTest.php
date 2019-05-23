<?PHP

# Requires PHP7
# Run with ./vendor/bin/phpunit --bootstrap vendor/autoload.php ToolforgeCommonTest.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
use PHPUnit\Framework\TestCase;

require_once ( __DIR__ . '/../public_html/php/wikiquery.php' ) ;

/**
 * @covers WikiQuery
 */
final class WikiQueryTest extends TestCase {

	public function test_get_api_base_url() :void {
		$wq = new WikiQuery ( 'de' , 'wikipedia' ) ;
		$this->assertEquals ( $wq->get_api_base_url() , 'http://de.wikipedia.org/w/api.php?format=php&' ) ;
		$this->assertEquals ( $wq->get_api_base_url('test') , 'http://de.wikipedia.org/w/api.php?format=php&action=query&prop=test&' ) ;
	}

	public function test_get_image_data() :void {
		$wq = new WikiQuery ( 'commons' ) ;
		$d = $wq->get_image_data ( 'File:Moscow State University crop.jpg' , 120 , 60 ) ;
		$this->assertEquals ( $d['id'] , 35852262 ) ;
	}

	public function test_does_image_exist() :void {
		$wq = new WikiQuery ( 'commons' ) ;
		$this->assertEquals ( $wq->does_image_exist ( 'File:Moscow State University crop.jpg' ) , true ) ;
		$this->assertEquals ( $wq->does_image_exist ( 'File:Some image that is really not there, I mean, totally.jpg' ) , false ) ;
	}

	public function test_get_existing_pages() :void {
		$wq = new WikiQuery ( 'en' , 'wikipedia' ) ;
		$existing_page = 'User:Magnus Manske' ;
		$non_existing_page = 'uib7iegvsi7vwfw7cefw7iewteic' ;
		$d = $wq->get_existing_pages([$existing_page,$non_existing_page]) ;
		$this->assertEquals ( $d , [$existing_page] ) ;
	}
}

?>
