<?php
/**
 * FanBox extension's hooked functions. All class methods are obviously public
 * and static.
 *
 * @file
 * @ingroup Extensions
 */
class FanBoxHooks {

	/**
	 * When a fanbox is moved to a new title, update the records in the fantag
	 * table.
	 *
	 * @param $title Object: Title object representing the old title
	 * @param $newtitle Object: Title object representing the new title
	 * @param $oldid Integer:
	 * @param $newid Integer:
	 * @return Boolean true
	 */
	public static function updateFanBoxTitle( &$title, &$newtitle, $user, $oldid, $newid ) {
		if ( $title->getNamespace() == NS_FANTAG ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'fantag',
				[ 'fantag_title' => $newtitle->getText() ],
				[ 'fantag_pg_id' => intval( $oldid ) ],
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * When a page in the NS_FANTAG namespace is deleted, delete all fantag
	 * records associated with that page.
	 *
	 * @param $article Object: instance of Article or its descendant class
	 * @param $user Object: the User performing the page deletion [unused]
	 * @param $reason String: user-supplied reason for the deletion [unused]
	 * @return Boolean true
	 */
	public static function deleteFanBox( &$article, &$user, $reason ) {
		if ( $article->getTitle()->getNamespace() == NS_FANTAG ) {
			$dbw = wfGetDB( DB_MASTER );

			$s = $dbw->selectRow(
				'fantag',
				[ 'fantag_pg_id', 'fantag_id' ],
				[ 'fantag_pg_id' => intval( $article->getID() ) ],
				__METHOD__
			);
			if ( $s !== false ) {
				// delete fanbox records
				$dbw->delete(
					'user_fantag',
					[ 'userft_fantag_id' => intval( $s->fantag_id ) ],
					__METHOD__
				);

				$dbw->delete(
					'fantag',
					[ 'fantag_pg_id' => intval( $article->getID() ) ],
					__METHOD__
				);
			}
		}

		return true;
	}

	/**
	 * Convert [[Fan:Fan Name]] tags to <fan></fan> hook
	 *
	 * @param $parser Unused
	 * @param $text String: text to search for [[Fan:]] links
	 * @param $strip_state Unused
	 * @return Boolean true
	 */
	public static function transformFanBoxTags( &$parser, &$text, &$strip_state ) {
		$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
		$fantitle = $contLang->getNsText( NS_FANTAG );
		$pattern = "@(\[\[$fantitle)([^\]]*?)].*?\]@si";
		$text = preg_replace_callback( $pattern, 'FanBoxHooks::renderFanBoxTag', $text );

		return true;
	}

	/**
	 * On preg_replace_callback
	 * Found a match of [[Fan:]], so get parameters and construct <fan> hook
	 *
	 * @param $matches Array
	 * @return String HTML
	 */
	public static function renderFanBoxTag( $matches ) {
		$name = $matches[2];
		$params = explode( '|', $name );
		$fan_name = $params[0];
		$fan_name = Title::newFromDBKey( $fan_name );

		if ( !is_object( $fan_name ) ) {
			return '';
		}

		$fan = FanBox::newFromName( $fan_name->getText() );

		if ( $fan->exists() ) {
			$output = "<fan name=\"{$fan->getName()}\"></fan>";
			return $output;
		}

		return $matches[0];
	}

	/**
	 * Calls FanBoxPage instead of standard Article for pages in the NS_FANTAG
	 * namespace.
	 *
	 * @param $title Title
	 * @param $article Article|WikiPage|FanBoxPage
	 * @return Boolean true
	 */
	public static function fantagFromTitle( &$title, &$article ) {
		global $wgRequest, $wgOut;

		if ( $title->getNamespace() == NS_FANTAG ) {
			// Add CSS
			$wgOut->addModuleStyles( 'ext.fanBoxes' );

			// Prevent normal edit attempts
			if ( $wgRequest->getVal( 'action' ) == 'edit' ) {
				$addTitle = SpecialPage::getTitleFor( 'UserBoxes' );
				$fan = FanBox::newFromName( $title->getText() );
				if ( !$fan->exists() ) {
					$wgOut->redirect( $addTitle->getFullURL( 'destName=' . $fan->getName() ) );
				} else {
					$update = SpecialPage::getTitleFor( 'UserBoxes' );
					$wgOut->redirect( $update->getFullURL( 'id=' . $title->getArticleID() ) );
				}
			}

			$article = new FanBoxPage( $title );
		}

		return true;
	}

	/**
	 * Register the new <fan> hook with the parser.
	 *
	 * @param $parser Parser
	 * @return Boolean true
	 */
	public static function registerFanTag( &$parser ) {
		$parser->setHook( 'fan', [ 'FanBoxHooks', 'embedFanBox' ] );
		return true;
	}

	/**
	 * Callback function for the registerFanTag() function that expands <fan>
	 * into valid HTML.
	 *
	 * @param $input
	 * @param $argv Array: array of user-supplied arguments
	 * @param $parser Parser
	 * @return String HTML
	 */
	public static function embedFanBox( $input, $argv, $parser ) {
		global $wgUser, $wgHooks;

		$parser->getOutput()->updateCacheExpiry( 0 );

		$wgHooks['BeforePageDisplay'][] = 'FanBoxHooks::addFanBoxScripts';

		$fan_name = $argv['name'];
		if ( !$fan_name ) {
			return '';
		}

		$fan = FanBox::newFromName( $fan_name );

		$output = '';
		if ( $fan->exists() ) {
			$output .= $fan->outputFanBox();
			$fantagId = $fan->getFanBoxId();

			$output .= '<div id="show-message-container' . intval( $fantagId ) . '">';
			if ( $wgUser->isLoggedIn() ) {
				$check = $fan->checkIfUserHasFanBox();
				if ( $check == 0 ) {
					$output .= $fan->outputIfUserDoesntHaveFanBox();
				} else {
					$output .= $fan->outputIfUserHasFanBox();
				}
			} else {
				$output .= $fan->outputIfUserNotLoggedIn();
			}

			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Add FanBox's CSS and JS into the page output.
	 *
	 * @param $out OutputPage
	 * @param $skin Skin
	 * @return Boolean true
	 */
	public static function addFanBoxScripts( &$out, &$skin ) {
		$out->addModules( 'ext.fanBoxes' );
		return true;
	}

	/**
	 * Creates the necessary database tables when the user runs
	 * maintenance/update.php, the core MediaWiki updater script.
	 *
	 * @param $updater DatabaseUpdater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$updater->addExtensionTable( 'fantag', $dir . '/fantag.sql' );
		$updater->addExtensionTable( 'user_fantag', $dir . '/fantag.sql' );

		$db = $updater->getDB();

		$fantagTableHasActorField = $db->fieldExists( 'fantag', 'fantag_actor', __METHOD__ );
		$userFantagTableHasActorField = $db->fieldExists( 'user_fantag', 'userft_actor', __METHOD__ );

		// Actor support
		if ( !$fantagTableHasActorField ) {
			// 1) add new actor column
			$updater->addExtensionField( 'fantag', 'fantag_actor', $dir . '/patches/actor/add_fantag_actor_field_to_fantag.sql' );

			// 2) add the corresponding index
			$updater->addExtensionIndex( 'fantag', 'fantag_actor', $dir . '/patches/actor/add_fantag_actor_index_to_fantag.sql' );
		}

		if ( !$userFantagTableHasActorField ) {
			// 1) add new actor column
			$updater->addExtensionField( 'user_fantag', 'userft_actor', $dir . '/patches/actor/add_userft_actor_field_to_user_fantag.sql' );

			// 2) add the corresponding index
			$updater->addExtensionIndex( 'user_fantag', 'userft_actor', $dir . '/patches/actor/add_userft_actor_index_to_user_fantag.sql' );
		}

		// The only time both tables have both an _actor and a _user_name column at
		// the same time is when upgrading from an older version to v. 1.9.0;
		// all versions prior to that will have only the _user_name columns (and the
		// corresponding _user_id columns, but we assume here that if the _user_name
		// columns are present, the _user_id ones must also be) and v. 1.9.0 and newer
		// will only have the _actor columns.
		// If both are present, then we know that we're in the middle of migration and
		// we should complete the migration ASAP.
		if (
			$db->fieldExists( 'fantag', 'fantag_actor', __METHOD__ ) &&
			$db->fieldExists( 'fantag', 'fantag_user_name', __METHOD__ ) &&
			$db->fieldExists( 'user_fantag', 'userft_actor', __METHOD__ ) &&
			$db->fieldExists( 'user_fantag', 'userft_user_name', __METHOD__ )
		) {
			// 3) populate the columns with correct values
			// PITFALL WARNING! Do NOT change this to $updater->runMaintenance,
			// THEY ARE NOT THE SAME THING and this MUST be using addExtensionUpdate
			// instead for the code to work as desired!
			// HT Skizzerz
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldFanBoxesUserColumnsToActor',
				'../maintenance/migrateOldFanBoxesUserColumnsToActor.php'
			] );

			// 4) drop old columns + indexes
			$updater->dropExtensionField( 'fantag', 'fantag_user_name', $dir . '/patches/actor/drop_fantag_user_name_field_from_fantag.sql' );
			$updater->dropExtensionField( 'fantag', 'fantag_user_id', $dir . '/patches/actor/drop_fantag_user_id_field_from_fantag.sql' );
			$updater->dropExtensionIndex( 'fantag', 'fantag_user_id', $dir . '/patches/actor/drop_fantag_user_id_index_from_fantag.sql' );

			// 4) drop old columns + indexes
			$updater->dropExtensionField( 'user_fantag', 'userft_user_name', $dir . '/patches/actor/drop_userft_user_name_field_from_user_fantag.sql' );
			$updater->dropExtensionField( 'user_fantag', 'userft_user_id', $dir . '/patches/actor/drop_userft_user_id_field_from_user_fantag.sql' );
			$updater->dropExtensionIndex( 'user_fantag', 'userft_user_id', $dir . '/patches/actor/drop_userft_user_id_index_from_user_fantag.sql' );
		}
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param $list Array: array of namespace numbers with corresponding
	 *                     canonical names
	 * @return Boolean true
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_FANTAG] = 'UserBox';
		$list[NS_FANTAG_TALK] = 'UserBox_talk';
		return true;
	}
}
