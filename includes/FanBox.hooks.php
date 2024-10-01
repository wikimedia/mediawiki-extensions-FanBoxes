<?php

use MediaWiki\MediaWikiServices;

/**
 * FanBox extension's hooked functions. All class methods are obviously public
 * and static.
 *
 * @file
 * @ingroup Extensions
 */
class FanBoxHooks {
	/**
	 * If a rollback took place, read the old revision data and update the fantag table accordingly to genuinely
	 * "commit" the rollback results.
	 *
	 * @see https://phabricator.wikimedia.org/T367313
	 *
	 * @param WikiPage $wikiPage The impacted page in the NS_FANTAG namespace
	 * @param MediaWiki\User\UserIdentity $user The user who did the rollback
	 * @param string $summary Edit summary
	 * @param int $flags
	 * @param MediaWiki\Revision\RevisionRecord $revisionRecord
	 * @param MediaWiki\Storage\EditResult $editResult
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		MediaWiki\User\UserIdentity $user,
		string $summary,
		int $flags,
		MediaWiki\Revision\RevisionRecord $revisionRecord,
		MediaWiki\Storage\EditResult $editResult
	) {
		$title = $wikiPage->getTitle();
		$revertMethod = $editResult->getRevertMethod();

		if ( $title->getNamespace() == NS_FANTAG && $revertMethod !== null ) {
			$originalRevId = $editResult->getOriginalRevisionId();
			if ( $originalRevId === 0 ) {
				// Should NOT happen!
				return;
			}

			$fb = FanBox::newFromOldId( $originalRevId );
			if ( $fb ) {
				// newFromOldId() calls setVariablesFromText() which ensures the correct class member variables (hopefully!)
				$fb->updateFan(
					$fb->getFanBoxLeftText(),
					$fb->getFanBoxLeftTextColor(),
					$fb->getFanBoxLeftBgColor(),
					$fb->getFanBoxRightText(),
					$fb->getFanBoxRightTextColor(),
					$fb->getFanBoxRightBgColor(),
					$fb->getFanBoxImage(),
					$fb->getFanBoxLeftTextSize(),
					$fb->getFanBoxRightTextSize(),
					$wikiPage->getId(),
					$fb->categories,
					$user,
					'',
					true // do NOT edit the wiki page again, it has already been edited!
				);
			}
		}
	}

	/**
	 * When a fanbox is moved to a new title, update the records in the fantag
	 * table.
	 *
	 * @param MediaWiki\Linker\LinkTarget $old Object representing the old title
	 * @param MediaWiki\Linker\LinkTarget $new Object representing the new title
	 * @param MediaWiki\User\UserIdentity $userIdentity
	 * @param int $oldid
	 * @param int $newid
	 * @param string $reason User-supplied reason for moving the page
	 * @param MediaWiki\Revision\RevisionRecord $revision
	 */
	public static function updateFanBoxTitle(
		MediaWiki\Linker\LinkTarget $old,
		MediaWiki\Linker\LinkTarget $new,
		MediaWiki\User\UserIdentity $userIdentity,
		int $oldid,
		int $newid,
		string $reason,
		MediaWiki\Revision\RevisionRecord $revision
	) {
		if ( $old->getNamespace() == NS_FANTAG ) {
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$dbw->update(
				'fantag',
				[ 'fantag_title' => $new->getText() ],
				[ 'fantag_pg_id' => $oldid ],
				__METHOD__
			);
		}
	}

	/**
	 * When a page in the NS_FANTAG namespace is deleted, delete all fantag
	 * records associated with that page.
	 *
	 * @param Article $article Instance of Article or its descendant class
	 * @param User $user The User performing the page deletion [unused]
	 * @param string $reason User-supplied reason for the deletion [unused]
	 */
	public static function deleteFanBox( $article, $user, $reason ) {
		if ( $article->getTitle()->getNamespace() == NS_FANTAG ) {
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			if ( method_exists( $article, 'getId' ) ) {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$pageId = $article->getId();
			} else {
				$pageId = $article->getPage()->getId();
			}

			$s = $dbw->selectRow(
				'fantag',
				[ 'fantag_pg_id', 'fantag_id' ],
				[ 'fantag_pg_id' => $pageId ],
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
					[ 'fantag_pg_id' => $pageId ],
					__METHOD__
				);
			}
		}
	}

	/**
	 * Complements the above function - handles the undeletion of UserBox: pages.
	 *
	 * @note For MW 1.40+ this needs to be swapped to the PageUndeleteComplete hook (which is 1.40+).
	 * @see https://phabricator.wikimedia.org/T367809
	 *
	 * @param Title $title
	 * @param bool $create Whether or not the restoration caused the page to be created (i.e. it didn't exist before)
	 * @param string $comment Undeletion reason supplied by the person undeleting the page
	 * @param int $oldPageId ID of page previously deleted (from archive table). This ID will be used for the restored page.
	 * @param array $restoredPages Set of page IDs that have revisions restored for the undelete, with
	 *   keys being page IDs and values are 'true'.
	 */
	public static function onArticleUndelete( $title, bool $create, string $comment, int $oldPageId, array $restoredPages ) {
		if ( $title->inNamespace( NS_FANTAG ) && $create ) {
			$services = MediaWikiServices::getInstance();
			$lookupService = $services->getRevisionLookup();
			$currentRevision = $lookupService->getRevisionByTitle( $title );
			if ( !$currentRevision ) {
				// ?!?
				return;
			}

			$currentContent = $currentRevision->getContent( MediaWiki\Revision\SlotRecord::MAIN );
			if ( !$currentContent ) {
				return;
			}

			'@phan-var Content $currentContent';
			if ( method_exists( $currentContent, 'getText' ) ) {
				// MW 1.39+ (at least)
				// @phan-suppress-next-line PhanUndeclaredMethod It's...not supposed to be undeclared
				$oldText = $currentContent->getText();
			} else {
				$oldText = ContentHandler::getContentText( $currentContent );
			}

			if ( !$oldText ) {
				// ?!?
				return;
			}
			$fb = ( new FanBox( $title ) )->setVariablesFromText( $oldText );

			$dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );

			$actor = RequestContext::getMain()->getUser()->getActorId();
			$firstRev = $lookupService->getFirstRevision( $title );
			$firstTS = null;
			if ( $firstRev !== null ) {
				$firstTS = $firstRev->getTimestamp();
				$actor = $firstRev->getUser();
				if ( $actor !== null ) {
					// FIXME:  Deprecated: Use of MediaWiki\User\UserIdentityValue::getActorId was deprecated in MediaWiki 1.36.
					# Wikimedia\AtEase\AtEase::suppressWarnings();
					# $actor = $actor->getActorId();
					# Wikimedia\AtEase\AtEase::restoreWarnings();
					// WTF is the difference between "findActorId" and "acquireActorId"? That's some real nice method naming there...
					$actor = $services->getActorNormalization()->acquireActorId( $actor, $dbw );
				}
			}

			$dbw->insert(
				'fantag',
				[
					'fantag_title' => $title->getText(),
					'fantag_pg_id' => $oldPageId,
					'fantag_left_text' => $fb->getFanBoxLeftText(),
					'fantag_left_textcolor' => $fb->getFanBoxLeftTextColor(),
					'fantag_left_bgcolor' => $fb->getFanBoxLeftBgColor(),
					'fantag_right_text' => $fb->getFanBoxRightText(),
					'fantag_right_textcolor' => $fb->getFanBoxRightTextColor(),
					'fantag_right_bgcolor' => $fb->getFanBoxRightBgColor(),
					'fantag_actor' => $actor,
					'fantag_date' => $dbw->timestamp( ( $firstTS ?? wfTimestampNow() ) ),
					'fantag_image_name' => $fb->getFanBoxImage(),
					'fantag_left_textsize' => $fb->getFanBoxLeftTextSize(),
					'fantag_right_textsize' => $fb->getFanBoxRightTextSize()
				],
				__METHOD__,
				// For good measure:
				[ 'IGNORE' ]
			);
		}
	}

	/**
	 * Prevent editing UserBox: pages via the API (api.php).
	 *
	 * @param ApiBase $module
	 * @param User $user
	 * @param IApiMessage|Message|string|array &$message
	 * @return bool
	 */
	public static function onApiCheckCanExecute( $module, $user, &$message ) {
		$moduleName = $module->getModuleName();

		if ( $moduleName == 'edit' ) {
			$params = $module->extractRequestParams();
			$pageObj = $module->getTitleOrPageId( $params );
			$titleObj = $pageObj->getTitle();

			if ( $titleObj->inNamespace( NS_FANTAG ) ) {
				$message = 'fanbox-error-no-api-edit';
				return false;
			}

			return true;
		}

		return true;
	}

	/**
	 * Convert [[Fan:Fan Name]] tags to <fan></fan> hook
	 *
	 * @param Parser $parser Unused
	 * @param string &$text Text to search for [[Fan:]] links
	 * @param StripState $strip_state Unused
	 */
	public static function transformFanBoxTags( $parser, &$text, $strip_state ) {
		$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
		$fantitle = $contLang->getNsText( NS_FANTAG );
		$pattern = "@(\[\[$fantitle)([^\]]*?)].*?\]@si";
		$text = preg_replace_callback( $pattern, 'FanBoxHooks::renderFanBoxTag', $text );
	}

	/**
	 * On preg_replace_callback
	 * Found a match of [[Fan:]], so get parameters and construct <fan> hook
	 *
	 * @param array $matches
	 * @return string HTML
	 */
	public static function renderFanBoxTag( $matches ) {
		$name = $matches[2];
		$params = explode( '|', $name );
		$fan_name = $params[0];
		$fan_name = Title::newFromDBkey( $fan_name );

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
	 * @param Title $title
	 * @param Article|WikiPage|FanBoxPage &$article
	 * @param RequestContext $context
	 */
	public static function fantagFromTitle( Title $title, &$article, $context ) {
		if ( $title->getNamespace() == NS_FANTAG ) {
			$out = $context->getOutput();
			$request = $context->getRequest();

			// Add CSS
			$out->addModuleStyles( 'ext.fanBoxes.styles' );

			// Prevent normal edit attempts
			if ( $request->getVal( 'action' ) == 'edit' ) {
				$editorSpecialPage = SpecialPage::getTitleFor( 'UserBoxes' );
				$fan = FanBox::newFromName( $title->getText() );

				if ( !$fan->exists() ) {
					$out->redirect( $editorSpecialPage->getFullURL( 'destName=' . $fan->getName() ) );
				} else {
					$params = [
						'id' => $title->getArticleID()
					];

					// Support the oldid, undo & undoafter parameters for editing non-current versions of a UserBox
					// (undoafter gets mapped back to oldid by SpecialFanBoxes.php anyway, but still)
					$oldId = $request->getInt( 'oldid' );
					$undo = $request->getInt( 'undo' );
					$undoAfter = $request->getInt( 'undoafter' );
					if ( $oldId ) {
						$params['oldid'] = $oldId;
					}
					if ( $undo ) {
						$params['undo'] = $undo;
					}
					if ( $undoAfter ) {
						$params['undoafter'] = $undoAfter;
					}

					$out->redirect( $editorSpecialPage->getFullURL( $params ) );
				}
			}

			$article = new FanBoxPage( $title );
		}
	}

	/**
	 * Register the new <fan> hook with the parser.
	 *
	 * @param Parser $parser
	 */
	public static function registerFanTag( $parser ) {
		$parser->setHook( 'fan', [ 'FanBoxHooks', 'embedFanBox' ] );
	}

	/**
	 * Callback function for the registerFanTag() function that expands <fan>
	 * into valid HTML.
	 *
	 * @param string $input
	 * @param array $argv User-supplied arguments
	 * @param Parser $parser
	 * @return string HTML
	 */
	public static function embedFanBox( $input, $argv, $parser ) {
		global $wgHooks;

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $parser->getUserIdentity() );
		$parser->getOutput()->updateCacheExpiry( 0 );

		// @todo FIXME: why not just use $parser->getOutput()->addModules/addModuleStyles()? --ashley, 14 November 2020
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
			if ( $user->isRegistered() ) {
				$check = $fan->checkIfUserHasFanBox( $user );
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
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function addFanBoxScripts( $out, $skin ) {
		$out->addModuleStyles( 'ext.fanBoxes.styles' );
		$out->addModules( 'ext.fanBoxes.scripts' );
	}

	/**
	 * Creates the necessary database tables when the user runs
	 * maintenance/update.php, the core MediaWiki updater script.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$db = $updater->getDB();
		$dbType = $db->getType();

		$fantag = 'fantag.sql';
		$userFantag = 'user_fantag.sql';
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$fantag = "fantag.{$dbType}.sql";
			$userFantag = "user_fantag.{$dbType}.sql";
		}

		$updater->addExtensionTable( 'fantag', "$dir/$fantag" );
		$updater->addExtensionTable( 'user_fantag', "$dir/$userFantag" );

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

		// Drop unused user_fantag.userft_order column (T242868)
		$updater->dropExtensionField( 'user_fantag', 'userft_order', $dir . '/patches/drop-userft_order.sql' );
	}
}
