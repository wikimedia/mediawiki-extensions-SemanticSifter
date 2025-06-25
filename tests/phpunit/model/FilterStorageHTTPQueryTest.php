<?php
/**
 * @author: Kim Eik
 */

namespace SemanticSifter;


class FilterStorageHTTPQueryTest extends \MediaWikiIntegrationTestCase {
	/**
	 * @var FilterStorageHTTPQuery
	 */
	private  $filterStorage;

	protected function setUp() {
		parent::setUp();
		$this->filterStorage = new FilterStorageHTTPQuery();
	}

	protected function tearDown() {
		unset( $this->filterStorage );
		unset($_GET['filter']);
		parent::tearDown();
	}

	public function testGetFiltersFromGET() {
		$_GET['filter'] = base64_encode( 'Property::Value' );
		$this->filterStorage = new FilterStorageHTTPQuery();
		$this->assertArrayEquals( array(
			'Property' => array( 'Value' )
		), $this->filterStorage->getFilters() );
	}

	public function testGetFiltersFromGETWhenInvalidFormat() {
		$this->expectException( \InvalidArgumentException::class );
		$_GET['filter'] = 'INVALID';
		$this->filterStorage = new FilterStorageHTTPQuery();
	}

	public function testAddFilter() {
		$this->assertInstanceOf(SemanticSifter\FilterStorageHTTPQuery::class,$this->filterStorage->addFilter('Property','Value'));
		$this->assertArrayEquals( array(
			'Property' => array( 'Value' )
		), $this->filterStorage->getFilters() );
	}

	public function testAddManyFilterByChaining() {
		$this->filterStorage->addFilter('Property','Value')->addFilter('Property','Value2')->addFilter('Property2','Value');
		$this->assertArrayEquals( array(
			'Property' => array( 'Value','Value2' ),
			'Property2' => array( 'Value' )
		), $this->filterStorage->getFilters() );
	}

	public function testRemoveFilter() {
		$this->filterStorage->addFilter('Property','Value');
		$this->filterStorage->addFilter('Property','Value2');
		$this->filterStorage->removeFilter('Property','Value');
		$this->assertArrayEquals( array(
			'Property' => array( 'Value2' )
		), $this->filterStorage->getFilters() );
	}

	public function testRemoveAllFiltersByProperty() {
		$this->filterStorage->addFilter('Property','Value');
		$this->filterStorage->addFilter('Property','Value2');
		$this->filterStorage->removeFilter('Property');
		$this->assertArrayEquals( array(), $this->filterStorage->getFilters() );
	}

	public function testGetFiltersAsHTTPQueryString() {
		$expected = 'filter=';
		$expected .= base64_encode('Property::Value');
		$expected .= ';';
		$expected .= base64_encode('Property::Value2');

		$this->filterStorage->addFilter('Property','Value');
		$this->filterStorage->addFilter('Property','Value2');
		$this->assertEquals( $expected, $this->filterStorage->getFiltersAsQueryString() );
	}

	public function testGetFiltersAsSeparatedString() {
		$expected = 'Property::Value';
		$expected .= ';';
		$expected .= 'Property::Value2';

		$this->filterStorage->addFilter('Property','Value');
		$this->filterStorage->addFilter('Property','Value2');
		$this->assertEquals( $expected, $this->filterStorage->getFiltersAsSeparatedString() );
	}

	public function testToggleFilter(){
		$this->filterStorage->toggleFilter('Property','Value');
		$this->assertArrayEquals( array(
			'Property' => array('Value')
		), $this->filterStorage->getFilters() );
		$this->filterStorage->toggleFilter('Property','Value');
		$this->assertArrayEquals( array(), $this->filterStorage->getFilters() );
	}

	public function testSetFiltersFromSeparatedString(){
		$string = 'Property::Value;Property::Value2';
		$this->assertArrayEquals(array(
			'Property' => array(
				'Value',
				'Value2'
			)
		),$this->filterStorage->setFiltersFromSeparatedString($string)->getFilters());
	}


	public function testSetFiltersFromSeparatedStringThatIsEmpty(){
		$string = '';
		$this->assertArrayEquals(array(),$this->filterStorage->setFiltersFromSeparatedString($string)->getFilters());
	}

}