<?php
/**
 * @author: Kim Eik
 */

namespace SemanticSifter\Components;

use \SMW\StoreFactory;
use SemanticSifter\Model\FilterStorageHTTPQuery;
/**
 * Class SemanticSifterComponent
 * @package SemanticSifter
 */
class SemanticSifterComponent {

	/**
	 * HTTP Query storage system for filter selection
	 * @var FilterStorageHTTPQuery
	 */
	protected $filterStorage;

	/**
	 * @var \Parser
	 */
	protected $parser;

	/**
	 * The title of which the component will be created
	 * @var \Title
	 */
	protected $title;



	/**
	 * @param $parser \Parser
	 * @param $filterStorage FilterStorageHTTPQuery
	 */
	function __construct(&$parser, $filterStorage = null) {
		if(is_null($filterStorage)){
			$filterStorage = new FilterStorageHTTPQuery();
		}
		$this->filterStorage = $filterStorage;
		$this->parser = $parser;
		$this->title = $parser->getTitle();
	}

	/**
	 * @param SMW\Store $smwStore
	 * @param SMW\DIProperty $property
	 * @return array
	 * @throws \Exception
	 */
	protected  function getPropertyValue( $smwStore, $property ) {
		$wikiPages = $smwStore->getAllPropertySubjects( $property );
		$v = array();
		foreach ( $wikiPages as $wp ) {
			foreach ( $smwStore->getPropertyValues( $wp, $property ) as $value ) {
				if ( $value instanceof \SMWDataItem ) {
					switch ( $value->getDIType() ) {
						case \SMWDataItem::TYPE_WIKIPAGE:
							$title = $value->getTitle()->getFullText();
							break;
						default:
							$title = $value->getSerialization();
							break;
					}
					if ( !array_key_exists( $title, $v ) ) {
						$v[$title] = null;
					}
					continue;
				}
				throw new \Exception( "Unknown value type, expected: SMWDataItem but got: " . get_class( $value ) );
			}
		}
		return array_keys($v);
	}

	public function getComponents($query,$properties,$smwParams = array(),$smwProperties = array()){
		//create smw query
		if(empty($smwProperties)){
			$smwProperties = $properties;
		}
		$tableComponent = $this->createSMWQueryComponent($query, $smwProperties, $smwParams);

		//get smw property information and generate filterering component
		$filteringComponent = $this->createFilteringComponent($properties);

		return array($filteringComponent,$tableComponent);
	}

	protected function createSMWQueryFilter() {
		$filters = $this->filterStorage->getFilters();
		$tableFilters = '';
		if ( !empty( $filters ) ) {
			foreach ($filters as $property => $values ) {
				$c = 0;
				$tableFilters .= "[[$property:: ";
				foreach ( $values as $value ) {
					if ( $c > 0 ) {
						$tableFilters .= '||';
					}
					$tableFilters .= $value;
					$c++;
				}
				$tableFilters .= ']]';
			}
		}
		return $tableFilters;
	}

	public function createSMWQueryComponent($query, $properties, $params = array() ) {
		$smwParams = array(
			'format' => 'broadtable',
			'default' => '<div class="ss-noresults">'.wfMessage('semanticsifter-message-no-results')->text().'</div>'
		);
		$smwParams = array_merge($smwParams,$params);

		$tableFilters = $this->createSMWQueryFilter();
		$tableQuery = "{{#ask: $query $tableFilters";
		foreach ( $properties as $property => $value ) {
			$tableQuery .= '|?'.(is_null($value) ? $property : "$property=$value");
		}

		foreach($smwParams as $name => $value){
			$tableQuery .= "|$name=$value";
		}
		$tableQuery .= '}}';

		return $tableQuery;
	}

	protected function createFilteringComponent($properties) {
		$smwStore = StoreFactory::getStore();
		$output = '<form class="ss-filteringform">';
		foreach ( $properties as $p => $v ) {
			$displayValue = is_null($v) ? $p : $v;
			$property = \SMWDIProperty::newFromUserLabel( $p );
			$pValues = $this->getPropertyValue( $smwStore, $property );
			$output .= "<div class=\"ss-propertyfilter\">";
			$output .= "<select name=\"$p\" class=\"ss-property-select\" data-placeholder=\"$displayValue\" title=\"$displayValue\" multiple>";
			foreach($pValues as $pValue){
				$selected = $this->filterStorage->filterExists($p,$pValue) ? 'selected' : '';
				$output .= "<option value=\"$pValue\" $selected>$pValue</option>";
			}
			$output .= "</select>";
			$output .= "</div>";
		}
		$output .= '<div><button type="submit">'.wfMessage('semanticsifter-button-apply-filter')->text().'</button></div>';
		$output .= '</form>';
		return $output;
	}
}