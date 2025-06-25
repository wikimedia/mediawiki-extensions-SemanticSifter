<?php

namespace SemanticSifter;

class SemanticSifterHooks {

	public static function parserFunctionInit( \Parser &$parser ) {
		$parser->setFunctionHook( 'sift', 'SemanticSifter\ParserFunction\Sift::parserHook' );
		$parser->setFunctionHook( 'siftlink', 'SemanticSifter\ParserFunction\SiftLink::parserHook' );
		return true;
	}
}
