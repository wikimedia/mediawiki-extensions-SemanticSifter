<?php

namespace SemanticSifter\API;

use SemanticSifter\Model\FilterStorageHTTPQuery;

class API {

	public static function filter($filters){
		$filterStorage = new FilterStorageHTTPQuery(false);
		$filters = json_decode($filters);
		foreach($filters as $property => $values){
			if(!is_array($values)){
				$values = array($values);
			}

			foreach($values as $value){
				$filterStorage->addFilter($property,$value);
			}
		}
		return $filterStorage->getFiltersAsQueryString(false);
	}
} 