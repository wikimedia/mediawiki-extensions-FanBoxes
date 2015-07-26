<?php
/**
 * FanBox extension
 * Defines a new namespace for fanboxes (NS_FANTAG, the namespace number is 600
 * by default) and some new special pages to add/view fanboxes.
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Robert Lefkowitz
 * @author Jack Phoenix <jack@countervandalism.net>
 * @link https://www.mediawiki.org/wiki/Extension:FanBox Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

// Extension credits that show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'FanBox',
	'version' => '3.2.0',
	'author' => array( 'Aaron Wright', 'David Pean', 'Robert Lefkowitz', 'Jack Phoenix' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:FanBoxes',
	'description' => 'A new way of creating and using userboxes, based on special pages',
	'license-name' => 'GPL-2.0+',
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.fanBoxes'] = array(
	'styles' => 'FanBoxes.css',
	'scripts' => 'FanBoxes.js',
	'messages' => array(
		'fanbox-mustenter-left', 'fanbox-mustenter-right',
		'fanbox-mustenter-right-or', 'fanbox-mustenter-title', 'fanbox-hash',
		'fanbox-choose-another', 'fanbox-upload-new-image'
	),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'FanBoxes',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.fanBoxes.colorpicker'] = array(
	'scripts' => 'color-picker.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'FanBoxes',
);

// Global fantag namespace reference
if ( !defined( 'NS_FANTAG' ) ) {
	define( 'NS_FANTAG', 600 );
}

if ( !defined( 'NS_FANTAG_TALK' ) ) {
	define( 'NS_FANTAG_TALK', 601 );
}

// Set up the new special pages
$wgMessagesDirs['FanBoxes'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['FanBoxesNamespaces'] = __DIR__ . '/FanBox.namespaces.php';

$wgAutoloadClasses['FanBox'] = __DIR__ . '/FanBoxClass.php';
$wgAutoloadClasses['SpecialFanBoxAjaxUpload'] = __DIR__ . '/MiniAjaxUpload.php';
$wgAutoloadClasses['FanBoxAjaxUploadForm'] = __DIR__ . '/MiniAjaxUpload.php';
$wgAutoloadClasses['FanBoxUpload'] = __DIR__ . '/MiniAjaxUpload.php';
$wgAutoloadClasses['FanBoxPage'] = __DIR__ . '/FanBoxPage.php';
$wgAutoloadClasses['FanBoxes'] = __DIR__ . '/SpecialFanBoxes.php';
$wgAutoloadClasses['TagCloud'] = __DIR__ . '/TagCloudClass.php';
$wgAutoloadClasses['TopFanBoxes'] = __DIR__ . '/SpecialTopFanBoxes.php';
$wgAutoloadClasses['UserFanBoxes'] = __DIR__ . '/FanBoxesClass.php';
$wgAutoloadClasses['ViewFanBoxes'] = __DIR__ . '/SpecialViewFanBoxes.php';

$wgSpecialPages['FanBoxAjaxUpload'] = 'SpecialFanBoxAjaxUpload';
$wgSpecialPages['UserBoxes'] = 'FanBoxes';
$wgSpecialPages['TopUserboxes'] = 'TopFanBoxes';
$wgSpecialPages['ViewUserBoxes'] = 'ViewFanBoxes';

// API module
$wgAutoloadClasses['ApiFanBoxes'] = __DIR__ . '/ApiFanBoxes.php';
$wgAPIModules['fanboxes'] = 'ApiFanBoxes';

// <userboxes> parser hook
require_once( 'UserBoxesHook.php' );

# Configuration settings
// Should we display comments on FanBox pages? Requires the Comments extension.
$wgFanBoxPageDisplay['comments'] = true;
# End configuration settings

// Hooked functions
$wgAutoloadClasses['FanBoxHooks'] = __DIR__ . '/FanBoxHooks.php';

$wgHooks['TitleMoveComplete'][] = 'FanBoxHooks::updateFanBoxTitle';
$wgHooks['ArticleDelete'][] = 'FanBoxHooks::deleteFanBox';
$wgHooks['ArticleFromTitle'][] = 'FanBoxHooks::fantagFromTitle';
$wgHooks['ParserBeforeStrip'][] = 'FanBoxHooks::transformFanBoxTags';
$wgHooks['ParserFirstCallInit'][] = 'FanBoxHooks::registerFanTag';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'FanBoxHooks::addTables';
$wgHooks['RenameUserSQL'][] = 'FanBoxHooks::onUserRename'; // For the Renameuser extension
$wgHooks['CanonicalNamespaces'][] = 'FanBoxHooks::onCanonicalNamespaces';
