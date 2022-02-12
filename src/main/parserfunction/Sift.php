<?php

namespace SemanticSifter\ParserFunction;

use Category;
use ParamProcessor\Processor;
use SemanticSifter\Model\FilterStorageHTTPQuery;
use SMW\StoreFactory;
use Title;

class Sift
{

	/**
	 * @var \Parser
	 */
	protected $parser;

	/**
	 * Array of sift parameters
	 * @var mixed
	 */
	protected $parameters;

	/**
	 * Array of format parameters
	 * @var array
	 */
	protected $formatParams = array();

	/**
	 * Array of semantic properties
	 * @var array
	 */
	protected $properties = array();

	/**
	 * Array of filterable smw properties
	 * @var array
	 */
	protected $filterProperties = array();

	/**
	 * HTTP Query storage system for filter selection
	 * @var FilterStorageHTTPQuery
	 */
	protected $filterStorage;

	/**
	 * The title of which the component will be created
	 * @var \Title
	 */
	protected $title;

	/**
	 * Array of overidden property values to be shown in filtering suggestions
	 * @var array
	 */
	protected $propertyValues = array();


	/**
	 * @var \SMW\Store
	 */
	protected $smwStore;

	/**
	 * @param $parser \Parser
	 * @param $parameters
	 * @param $filterStorage FilterStorageHTTPQuery
	 */
	function __construct(&$parser, $parameters, $filterStorage = null)
	{
		if (is_null($filterStorage)) {
			$filterStorage = new FilterStorageHTTPQuery();
		}
		$this->filterStorage = $filterStorage;
		$this->parser = $parser;
		$this->title = $parser->getTitle();
		$this->parseParameters($parameters);

		$this->smwStore = StoreFactory::getStore();
	}

	/**
	 * @param \SMW\DIProperty $property
	 * @throws \Exception
	 * @return array
	 */
	private function getPropertyValue($property)
	{
		if(array_key_exists($property->getLabel(),$this->propertyValues)){
			return $this->propertyValues[$property->getLabel()];
		}

		$wikiPages = $this->smwStore->getAllPropertySubjects($property);
		$v = array();
		foreach ($wikiPages as $wp) {
			foreach ($this->smwStore->getPropertyValues($wp, $property) as $value) {
				if ($value instanceof \SMWDataItem) {
					switch ($value->getDIType()) {
						case \SMWDataItem::TYPE_WIKIPAGE:
							$title = $value->getTitle()->getFullText();
							break;
						default:
							$title = $value->getSerialization();
							break;
					}
					if (!array_key_exists($title, $v)) {
						$v[$title] = null;
					}
					continue;
				}
				throw new \Exception("Unknown value type, expected: SMWDataItem but got: " . get_class($value));
			}
		}
		return array_keys($v);
	}

	/**
	 * Returns the SMW query filter
	 * @return string
	 */
	private function createSMWQueryFilter()
	{
		$filters = $this->filterStorage->getFilters();
		$filteredQuery = '';
		if (!empty($filters)) {
			foreach ($filters as $property => $values) {
				$c = 0;
				$filteredQuery .= "[[$property:: ";
				foreach ($values as $value) {
					if ($c > 0) {
						$filteredQuery .= '||';
					}
					$filteredQuery .= $value;
					$c++;
				}
				$filteredQuery .= ']]';
			}
		}
		return $filteredQuery;
	}

	/**
	 * Returns the SMW component
	 * @return string
	 */
	private function createSMWQueryComponent()
	{
		$tableQuery = '';

		$tableFilters = $this->createSMWQueryFilter();
		$tableQuery .= "{{#ask: {$this->parameters['query']->getValue()} $tableFilters";
		foreach ($this->properties as $property => $value) {
			$tableQuery .= "\n|?" . (is_null($value) ? $property : "$property=$value");
		}

		foreach ($this->formatParams as $name => $value) {
			$tableQuery .= "\n|$name=$value";
		}
		$tableQuery .= "\n}}";

		return $tableQuery;
	}

	/**
	 * Returns filtering component
	 * @return string
	 */
	private function createFilteringComponent()
	{

		$output = '<form class="ss-filteringform">';
		foreach ($this->filterProperties as $p => $v) {
			$displayValue = is_null($v) ? $p : $v;
			$property = \SMWDIProperty::newFromUserLabel($p);
			$pValues = $this->getPropertyValue($property);
			$output .= "<div class=\"ss-propertyfilter\">";
			$output .= "<select name=\"$p\" class=\"ss-property-select\" data-placeholder=\"$displayValue\" title=\"$displayValue\" multiple>";
			foreach ($pValues as $pValue) {
				$selected = $this->filterStorage->filterExists($p, $pValue) ? 'selected' : '';
				$output .= "<option value=\"$pValue\" $selected>$pValue</option>";
			}
			$output .= "</select>";
			$output .= "</div>";
		}
		$output .= '<div><button type="submit">' . wfMessage('semanticsifter-button-apply-filter')->text() . '</button></div>';
		$output .= '</form>';
		return $output;
	}

	/**
	 * Parses the range of parameters and divides them into sift parameters, smw properties and format parameters.
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 */
	private function parseParameters(array $parameters)
	{

		foreach ($parameters as $key => &$arg) {
			if($key === 0){
				continue;
			}

			$arg = trim($arg);
			$arg = explode(';',$arg);
			$additionalProps = array_slice($arg,1);
			$arg = $arg[0];

			// parse <property> = <value>
			list($property, $value) = array_pad(explode('=', $arg, 2), 2, null);

			$flag = substr($property, 0, 1);
			if($flag === '?' || $flag === '!'){
				$property = substr($property,1);
				if($flag === '!'){
					//Extract smw property into filter array
					$this->filterProperties[$property] = $value;
					unset($parameters[$key]);
				}else if($flag === '?'){
					//set property of type |?=... as format parameter, used in conjunction with the mainlabel parameter
					if ($property === false) {
						$this->formatParams['?'] = $value;
					}else{
						//Extract smw property into its own array
						$this->properties[$property] = $value;
					}
					unset($parameters[$key]);
				}
				//parse additional property parameters
				$this->parsePropertyParameters($property,$additionalProps);
			}else{
				$siftParameter = false;
				foreach ($this->getParametersDefinitions() as $parameterDef) {
					if ($parameterDef['name'] === $property) {
						$siftParameter = true;
						break;
					}
				}

				if (!$siftParameter) {
					$this->formatParams[$property] = $value;
					unset($parameters[$key]);
				}
			}


		}

		/**
		 * If the list of filterable properties is zero, add all smw properties to the array.
		 */
		if(count($this->filterProperties) === 0){
			$this->filterProperties = $this->properties;
		}

		$defaultParams = array(
			array(
				0 => 'query',
				1 => Processor::PARAM_UNNAMED,
			)
		);


		$processor = Processor::newDefault();
		$processor->setFunctionParams($parameters, $this->getParametersDefinitions(), $defaultParams);
		$processedParams = $processor->processParameters();

		if ($processedParams->hasFatal() || count($processedParams->getErrors()) > 0) {
			throw new \InvalidArgumentException("Something went wrong when parsing arguments");
		}

		$this->parameters = $processedParams->getParameters();
	}

	/**
	 * @param $property string
	 * @param $parameters array
	 */
	private function parsePropertyParameters($property, $parameters){
		foreach ($parameters as $parameter){
			list($key,$value) = explode('=',$parameter);
			switch($key){
				case 'values':
					$this->propertyValues[$property] = explode(',',$value);
					break;
				case 'values from category':
					$this->propertyValues[$property] = $this->getValuesFromCategory($value);
					break;
				case 'values from subpage':
					$this->propertyValues[$property] = $this->getValuesFromSubpage($value);
					break;
			}
		}
	}

	/**
	 * Returns the final output
	 * @return string
	 */
	private function getOutput()
	{
		$queryOutput =  $filteringComponent = '';
		switch($this->parameters['display']->getValue()){
			case 'filter':
				$filteringComponent = $this->createFilteringComponent();
				break;
			case 'result':
				$queryOutput = $this->createSMWQueryComponent();
				break;
			case 'both':
			default:
				$queryOutput = $this->createSMWQueryComponent();
				$filteringComponent = $this->createFilteringComponent();
				break;
		}

		//create final html output
		$id = uniqid();
		$jsonParams = array('filterbox-width' => $this->parameters['filterwidth']->getValue());
		$jsonParams = json_encode($jsonParams);
		$debugOut = array_key_exists('debug',$_GET) && $_GET['debug'] === 'true' ? '<pre>'.$queryOutput.'</pre>' : '';
		$output = <<<EOT
			<script type="text/javascript">
				if(window.SemanticSifter === undefined){
					window.SemanticSifter = {};
				}
				window.SemanticSifter['$id'] = $jsonParams;
			</script>
			<div id="$id" class="ss-container" style="display: none;">
					{$debugOut}
					{$filteringComponent}
					<div style="overflow:auto">
						{$this->parser->recursiveTagParse($queryOutput)}
					</div>
			</div>
EOT;
		return $output;

	}

	/**
	 * The parserhook for sift
	 * @param \Parser $parser
	 * @return array|\Exception
	 */
	public static function parserHook(\Parser &$parser)
	{
		$parser->getOutput()->addModules(['ext.semanticsifter']);
		$parser->getOutput()->updateCacheExpiry( 0 );

		$args = func_get_args();
		array_shift($args);
		$sift = new Sift($parser, $args);

		try {
			return array(
				$sift->getOutput(),
				'noparse' => true,
				'isHTML' => true
			);
		} catch (\Exception $e) {
			//TODO add a more user friendly error
			return $e;
		}
	}

	/**
	 * @return array
	 */
	private function getParametersDefinitions()
	{
		return array(
			array(
				'name' => 'query',
				'default' => '',
				'message' => 'semanticsifter-param-query'
			),
			array(
				'name' => 'filterwidth',
				'default' => '20%',
				'message' => 'semanticsifter-param-filterwidth'
			),
			array(
				'name' => 'display',
				'default' => 'both',
				'message' => 'semanticsifter-param-display'
			)
		);
	}

	/**
	 * @param $category string
	 * @return array
	 */
	private function getValuesFromCategory($category){
		global $wgParser;
		$category = $wgParser->recursiveTagParse($category);
		$category = Title::newFromText($category);
		if ( is_null( $category ) ) {
			return array();
		}
		$category = Category::newFromTitle($category);
		$catMembers = $category->getMembers();
		$result = array();
		while($catMembers->valid()){
			/**
			 * @var $member Title
			 */
			$member = $catMembers->current();
			$result[] = $member->getText();
			$catMembers->next();
		}
		return $result;
	}

	/**
	 * @param $title string
	 * @return array
	 */
	private function getValuesFromSubpage($title){
		global $wgParser;
		$title = $wgParser->recursiveTagParse($title);
		$title = Title::newFromText($title);
		if ( is_null( $title ) ) {
			return array();
		}

		$subPages = $title->getSubpages();
		if(empty($subPages)){
			return array();
		}
		/** @var Title[] $titles */
		$titles[ ] = $title;
		while ( $subPages->valid() ) {
			$titles[ ] = $subPages->current();
			$subPages->next();
		}

		$result = array();
		foreach ( $titles as $title ) {
			$titleText = $title->getFullText();
			$titleTextParts = explode( '/', $titleText );
			$titleText = '';
			foreach ( $titleTextParts as $part ) {
				$titleText .= $part;
				$result[ $titleText ] = null;
				$titleText .= '/';
			}
		}
		return array_keys($result);
	}
} 