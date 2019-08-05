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
	 * @return Boolean true
	 */
	public static function addTables( $updater ) {
		$file = __DIR__ . '/../sql/fantag.sql';
		$updater->addExtensionTable( 'fantag', $file );
		$updater->addExtensionTable( 'user_fantag', $file );
		return true;
	}

	/**
	 * For the Renameuser extension.
	 *
	 * @param $renameUserSQL
	 * @return Boolean true
	 */
	public static function onUserRename( $renameUserSQL ) {
		$renameUserSQL->tables['fantag'] = [
			'fantag_user_name', 'fantag_user_id'
		];
		$renameUserSQL->tables['user_fantag'] = [
			'userft_user_name', 'userft_user_id'
		];
		return true;
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
