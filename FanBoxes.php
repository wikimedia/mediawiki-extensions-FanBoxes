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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'FanBoxes' );
	$wgMessagesDirs['FanBoxes'] =  __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for FanBoxes extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the FanBoxes extension requires MediaWiki 1.25+' );
}