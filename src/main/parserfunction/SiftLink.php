<?php

namespace SemanticSifter\ParserFunction;

use SemanticSifter\Model\FilterStorageHTTPQuery;

class SiftLink {

	public static function parserHook(\Parser &$parser,$title, $filters, $text){
		if(is_null($title) || is_null($filters) || is_null($text)){
			return wfMessage('semanticsifter-message-siftlink-params-error');
		}

		try{
			$filterStorage = new FilterStorageHTTPQuery(false);
			$filterStorage->setFiltersFromSeparatedString($filters);

			$title = \Title::newFromText($parser->recursiveTagParse($title));

			$filters = $filterStorage->size() > 0 ? array(
				FilterStorageHTTPQuery::QUERY_KEY => $filterStorage->getFiltersAsQueryString(false)
			) : array();

			$output = \Linker::link($title,$text,array('title' => null),$filters);

			return array($output, 'noparse' => true, 'isHTML' => true );
		}catch (\Exception $e){
			//TODO add a more user friendly error
			return $e;
		}
	}

} 