<?php

namespace SemanticSifter\ParserFunction;


use ParamProcessor\ProcessedParam;
use ParamProcessor\ProcessingResult;
use ParamProcessor\Processor;
use SemanticSifter\Components\SemanticSifterComponent;

class Sift {

	private $component;

	public function __construct(\Parser &$parser){
		$this->component = new SemanticSifterComponent($parser);
	}

	public static function parserHook(\Parser &$parser){
		$parser->getOutput()->addModules( 'ext.semanticsifter' );
		$parser->disableCache();

		try{
			//get parameters
			$args = func_get_args();
			array_shift($args);
			$args = self::getParameters( $args );
			$parameters = $args->getParameters();

			if(!$parameters){
				throw new \InvalidArgumentException("Error with parameters.");
			}

			$properties = array();
			$smwProperties = array();
			$smwParams = array();
			foreach($parameters as $param){
				if(strpos($param->getName(),'?') === 0){
					$properties[substr($param->getName(),1)] = $param->getValue();
				}elseif(strpos($param->getName(),'!?') === 0){
					$smwProperties[substr($param->getName(),2)] = $param->getValue();
				}elseif(strpos($param->getName(),'!') === 0){
					$smwParams[substr($param->getName(),1)] = $param->getValue();
				}
			}

			$component = new SemanticSifterComponent($parser);
			list($filteringComponent,$tableComponent) = $component->getComponents(
				$parameters['query']->getValue(),
				$properties,
				$smwParams,
				$smwProperties
			);

			//create final html output
			$id = uniqid();
			$jsonParams = array(
				'filterbox-width' => $parameters['filterbox-width']->getValue(),
			);
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
							{$parser->recursiveTagParse($tableComponent)}
						</div>
				</div>
EOT;
		}catch (\Exception $e){
			//TODO add a more user friendly error
			return $e;
		}
		$output = str_replace(array("\r","\n","\t"),array('','',''),$output);
		return array($output, 'noparse' => true, 'isHTML' => true );
	}


	private static function getParameters( array $args = array() ){
		//Hackish use of ParamProcessor to make input parameters similar to #ask parser function input
		$properties = array();
		foreach($args as $key => &$arg){
			$arg = trim($arg);
			switch(substr($arg,0,1)){
				case '?':
				case '!':
					list($property,$display) = array_pad(explode('=',$arg,2), 2, null);
					$properties[$property] = new ProcessedParam($property,$display,false);
					unset($args[$key]);
					break;
				default:
					break;
			}
		}

		$parameterDefs = array(
			array(
				'name' => 'query',
				'default' => '',
				'message' => 'semanticsifter-param-query',
			),
			array(
				'name' => 'filterbox-width',
				'default' => '20%',
				'message' => 'semanticsifter-param-filterbox-width',
			)
		);

		$defaultParams = array(
			array(
				0 => 'query',
				1 => Processor::PARAM_UNNAMED,
			)
		);


		$processor = Processor::newDefault();
		$processor->setFunctionParams($args,$parameterDefs,$defaultParams);
		$processedParams = $processor->processParameters();

		//Postprocess params
		$processedParams = new ProcessingResult(array_merge($processedParams->getParameters(),$properties),$processedParams->getErrors());
		if($processedParams->hasFatal()){
			return false;
		}
		return $processedParams;
	}
} 