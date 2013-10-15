<?php

namespace SemanticSifter;

class SemanticSifterHooks {

	public static function unitTestsInit( &$files ) {
		$testDir = __DIR__ . '/src/test';
		$files = array_merge( $files, glob( "$testDir/*Test.php" ) );
		return true;
	}

	public static function parserFunctionInit( \Parser &$parser ) {
		$parser->setFunctionHook( 'sift', 'SemanticSifter\ParserFunction\Sift::parserHook' );
		$parser->setFunctionHook( 'siftlink', 'SemanticSifter\ParserFunction\SiftLink::parserHook' );
		return true;
	}
}