<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

if ( !defined( 'SMW_VERSION' ) ) {
    echo( "SemanticSifter extension requires SemanticMediaWiki\n" );
    die( 1 );
}

if ( !defined( 'ParamProcessor_VERSION' ) ) {
	echo( "SemanticSifter extension requires ParamProcessor\n" );
	die( 1 );
}

function endsWith($haystack, $needle){
	return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

spl_autoload_register( function ( $className ) {
	$className = ltrim( $className, '\\' );
	$fileName = '';
	$namespace = '';
	$lastNsPos = strripos( $className, '\\');

	if ( $lastNsPos ) {
		$namespace = substr( $className, 0, $lastNsPos );
		$className = substr( $className, $lastNsPos + 1 );
		$fileName  = str_replace( '\\', '/', $namespace ) . '/';
	}

	$fileName .= str_replace( '_', '/', $className ) . '.php';

	$namespaceSegments = explode( '\\', $namespace );

	if ( array_shift($namespaceSegments) === 'SemanticSifter' ) {
		$fileName = substr($fileName,$lastNsPos);
		$namespaceSegments = array_map('strtolower', $namespaceSegments);

		if(endsWith($fileName,'Test.php')){
			require_once (__DIR__ . '/src/test/' . join('/',$namespaceSegments) . $fileName);
		}else{
			require_once (__DIR__ . '/src/main/' . join('/',$namespaceSegments) . $fileName);
		}
	}
} );

call_user_func( function() {
	global $wgExtensionCredits,$wgHooks,$wgExtensionMessagesFiles,$wgResourceModules,$wgAjaxExportList;

	//credits
	$wgExtensionCredits['parserhook'][] = array(
		'path' => __FILE__,
		'name' => 'Semantic Sifter',
		'descriptionmsg' => 'semanticsifter-desc',
		'version' => '0.1',
		'author' => 'Kim Eik',
	);

	//ajax
	$wgAjaxExportList[] = 'SemanticSifter\API\API::filter';

	//hooks
	$wgHooks['ParserFirstCallInit'][] = 'SemanticSifter\SemanticSifterHooks::parserFunctionInit';
	$wgHooks['UnitTestsList'][] = 'SemanticSifter\SemanticSifterHooks::unitTestsInit';

	//i18n
	$wgExtensionMessagesFiles['SemanticSifter'] = dirname( __FILE__ ) . '/SemanticSifter.i18n.php';
	$wgExtensionMessagesFiles['SemanticSifterMagic'] = dirname( __FILE__ ) . '/SemanticSifter.i18n.magic.php';

	//resource modules
	$wgResourceModules['ext.semanticsifter'] = array(
		'localBasePath' => dirname( __FILE__ ),
		'remoteExtPath' => 'SemanticSifter',
		'dependencies' => array( 'jquery.chosen','mediawiki.Uri' ),
		'scripts' => '/resources/main/js/ext.semanticsifter.js',
		'styles' => '/resources/main/css/ext.semanticsifter.css'
	);
});