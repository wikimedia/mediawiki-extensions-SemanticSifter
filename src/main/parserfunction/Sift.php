<?php

namespace SemanticSifter\ParserFunction;

use ParamProcessor\Processor;
use SemanticSifter\Model\FilterStorageHTTPQuery;
use SMW\StoreFactory;

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
	}

	/**
	 * @param \SMW\Store $smwStore
	 * @param \SMW\DIProperty $property
	 * @return array
	 * @throws \Exception
	 */
	private function getPropertyValue($smwStore, $property)
	{
		$wikiPages = $smwStore->getAllPropertySubjects($property);
		$v = array();
		foreach ($wikiPages as $wp) {
			foreach ($smwStore->getPropertyValues($wp, $property) as $value) {
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
			$tableQuery .= '|?' . (is_null($value) ? $property : "$property=$value");
		}

		foreach ($this->formatParams as $name => $value) {
			$tableQuery .= "|$name=$value";
		}
		$tableQuery .= '}}';

		return $tableQuery;
	}

	/**
	 * Returns filtering component
	 * @return string
	 */
	private function createFilteringComponent()
	{
		$smwStore = StoreFactory::getStore();
		$output = '<form class="ss-filteringform">';
		foreach ($this->properties as $p => $v) {
			$displayValue = is_null($v) ? $p : $v;
			$property = \SMWDIProperty::newFromUserLabel($p);
			$pValues = $this->getPropertyValue($smwStore, $property);
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
			$arg = trim($arg);
			list($property, $value) = array_pad(explode('=', $arg, 2), 2, null);
			switch (substr($arg, 0, 1)) {
				case '?':
					//case '!': //FIXME why is this here?
					//Extract smw property into its own array
					$this->properties[substr($property, 1)] = $value;
					unset($parameters[$key]);
					break;
				default:
					if ($key !== 0) {
						$siftParameter = false;
						foreach ($this->getParametersDefinitions() as $parameterDef) {
							if ($parameterDef['name'] === $property) {
								$siftParameter = true;
							}
						}

						if (!$siftParameter) {
							$this->formatParams[$property] = $value;
							unset($parameters[$key]);
						}
					}
					break;
			}

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
	 * Returns the final output
	 * @return string
	 */
	private function getOutput()
	{
		$queryOutput = $this->createSMWQueryComponent();
		$filteringComponent = $this->parameters['showfilter']->getValue() ?  $this->createFilteringComponent() : '';

		//create final html output
		$id = uniqid();
		$jsonParams = array('filterbox-width' => $this->parameters['filterwidth']->getValue());
		$jsonParams = json_encode($jsonParams);
		$output = <<<EOT
			<script type="text/javascript">
				if(window.SemanticSifter === undefined){
					window.SemanticSifter = {};
				}
				window.SemanticSifter['$id'] = $jsonParams;
			</script>
			<div id="$id" class="ss-container" style="display: none;">
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
		$parser->getOutput()->addModules('ext.semanticsifter');
		$parser->disableCache();

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
				'name' => 'showfilter',
				'default' => true,
				'type' => 'boolean',
				'message' => 'semanticsifter-param-showfilter'
			),
		);
	}
} 