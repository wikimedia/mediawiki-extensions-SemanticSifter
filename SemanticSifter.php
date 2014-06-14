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

global $wgExtensionCredits,$wgHooks,$wgExtensionMessagesFiles,$wgMessagesDirs,$wgResourceModules,$wgAjaxExportList;

//credits
$wgExtensionCredits['semantic'][] = array(
	'path' => __FILE__,
	'name' => 'Semantic Sifter',
	'descriptionmsg' => 'semanticsifter-desc',
	'version' => '0.1.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:SemanticSifter',
	'author' => 'Kim Eik',
	'license-name' => 'GPL-3.0'
);

//ajax
$wgAjaxExportList[] = 'SemanticSifter\API\API::filter';

//hooks
$wgHooks['ParserFirstCallInit'][] = 'SemanticSifter\SemanticSifterHooks::parserFunctionInit';
$wgHooks['UnitTestsList'][] = 'SemanticSifter\SemanticSifterHooks::unitTestsInit';

//i18n
$wgMessagesDirs['SemanticSifter'] = __DIR__ . '/i18n';
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
