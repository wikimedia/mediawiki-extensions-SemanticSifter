<?php
/**
 * @author: Kim Eik
 */

namespace SemanticSifter\Model;

/**
 * Class FilterStorageHTTPQuery
 * @package SemanticSifter
 */
class FilterStorageHTTPQuery{
	const QUERY_KEY = 'filter';
	const FILTER_SEPARATOR = ";";
	const PROPERTY_VALUE_SEPARATOR = "::";


	private $filters = array();

	/**
	 * @param bool $loadFromGET
	 */
	function __construct( $loadFromGET = true ) {
		$loadFromGET = $loadFromGET && array_key_exists(self::QUERY_KEY,$_GET);
		if($loadFromGET){
			$filters = explode(self::FILTER_SEPARATOR,urldecode($_GET[self::QUERY_KEY]));
			foreach($filters as $filter){
				if(!empty($filter)){
					list($property, $value) = $this->getDecodedFilterString($filter);
					$this->addFilter($property,$value);
				}
			}
		}
	}

	/**
	 * @param $property
	 * @param $value
	 * @return $this
	 */
	public function addFilter($property,$value){
		$this->filters[$property][$value] = null;
		return $this;
	}

	/**
	 * @param $property
	 * @param $value
	 * @return $this
	 */
	public function removeFilter($property,$value = null){
		if(is_null($value)){
			unset($this->filters[$property]);
		}else{
			unset($this->filters[$property][$value]);
			if(count($this->filters[$property]) === 0){
				$this->removeFilter($property);
			}
		}
		return $this;
	}

	public function toggleFilter($property,$value){
		if(array_key_exists($property,$this->filters) && array_key_exists($value,$this->filters[$property])){
			$this->removeFilter($property,$value);
		}else{
			$this->addFilter($property,$value);
		}
		return $this;
	}

	/**
	 * @return array
	 */
	public function getFilters() {
		$filters = $this->filters;
		foreach(array_keys($filters) as $key){
			$filters[$key] = array_keys($filters[$key]);
		}
		return $filters;
	}

	/**
	 * @return string
	 */
	public function getFiltersAsQueryString($prefixQueryKey = true) {
		$filters = $this->getFilters();
		$queryArray = array();
		foreach($filters as $property => $values){
			foreach($values as $value){
				$queryArray[] = $this->getEncodedFilterString($property,$value);
			}
		}
		if(count($queryArray) > 0){
			$queryStr = implode(self::FILTER_SEPARATOR,$queryArray);
			if($prefixQueryKey){
				$queryStr = self::QUERY_KEY.'='.$queryStr;
			}
			return $queryStr;
		}
		return '';
	}

	/**
	 * @return string
	 */
	public function getFiltersAsSeparatedString() {
		$filters = $this->getFilters();
		$queryArray = array();
		foreach($filters as $property => $values){
			foreach($values as $value){
				$queryArray[] = $this->getFilterString($property,$value);
			}
		}
		$queryStr = implode(self::FILTER_SEPARATOR,$queryArray);
		return $queryStr;
	}


	/**
	 * @param $filters
	 * @throws \InvalidArgumentException
	 * @return $this
	 */
	public function setFiltersFromSeparatedString($filters) {
		if(!empty($filters)){
			$filters = explode(self::FILTER_SEPARATOR,$filters);
			foreach($filters as $filter){
				if(substr_count($filter,self::PROPERTY_VALUE_SEPARATOR) !== 1){
					throw new \InvalidArgumentException("'$filter'' does not match the expected format <property>".
					self::PROPERTY_VALUE_SEPARATOR."<value>".
					self::FILTER_SEPARATOR."[<property>".
					self::PROPERTY_VALUE_SEPARATOR."<value>]...");
				}
				list($property,$value) = explode(self::PROPERTY_VALUE_SEPARATOR,$filter);
				$this->addFilter($property,$value);
			}
		}
		return $this;
	}

	/**
	 * @return int
	 */
	public function size(){
		return count($this->filters);
	}

	public function filterExists($property,$value){
		return array_key_exists($property,$this->filters) && array_key_exists($value,$this->filters[$property]);
	}

	/**
	 * @param $property
	 * @param $value
	 * @return string
	 */
	private function getEncodedFilterString($property,$value){
		return base64_encode($this->getFilterString($property,$value));
	}

	/**
	 * @param $property
	 * @param $value
	 * @return string
	 */
	private function getFilterString($property, $value){
		return $property.self::PROPERTY_VALUE_SEPARATOR.$value;
	}

	/**
	 * @param $input
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	private function getDecodedFilterString($input){
		$filter = base64_decode($input,true);
		if($filter !== false && substr_count($filter,self::PROPERTY_VALUE_SEPARATOR) === 1){
			$filterParts = explode(self::PROPERTY_VALUE_SEPARATOR,$filter);
			return $filterParts;
		}
		throw new \InvalidArgumentException("'$input ($filter)' does not match the expected format <property>".self::PROPERTY_VALUE_SEPARATOR."<value>");
	}

}