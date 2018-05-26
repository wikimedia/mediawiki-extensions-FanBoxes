<?php
/**
 * Translations of the UserBox namespace.
 *
 * @file
 */

$namespaceNames = [];

// For wikis where the FanBoxes extension is not installed.
if ( !defined( 'NS_FANTAG' ) ) {
	define( 'NS_FANTAG', 600 );
}

if ( !defined( 'NS_FANTAG_TALK' ) ) {
	define( 'NS_FANTAG_TALK', 601 );
}

/** English */
$namespaceNames['en'] = [
	NS_FANTAG => 'UserBox',
	NS_FANTAG_TALK => 'UserBox_talk',
];

/** Finnish (Suomi) */
$namespaceNames['fi'] = [
	NS_FANTAG => 'Käyttäjälaatikko',
	NS_FANTAG_TALK => 'Keskustelu_käyttäjälaatikosta',
];

/** Dutch (Nederlands) */
$namespaceNames['nl'] = [
	NS_FANTAG => 'Gebruikers_box',
	NS_FANTAG_TALK => 'Overleg_gebruikers_box',
];
