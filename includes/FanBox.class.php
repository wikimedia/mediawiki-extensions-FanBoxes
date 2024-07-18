<?php
/**
 * FanBox class - utilities for creating and managing fanboxes
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class FanBox {

	/** @var string UserBox page name in DBkey format (i.e. underscores instead of spaces) */
	public $name;

	/** @var Title Title object referring to the UserBox page */
	public $title;

	/** @var bool Does the UserBox in question actually...exist? */
	public $exists = false;

	/** @var int|bool Revision ID (revision.rev_id) of a non-current revision, or bool false if viewing the current revision */
	public $revisionId = false;

	/** @var int UserBox ID (_not_ identical to pg_id!), i.e. fantag.fantag_id from the DB */
	public $id;

	/** @var int UserBox page ID */
	public $pg_id;

	/** @var int Actor ID of the person (User) who created the UserBox in question */
	public $actor;

	/** @var string Left-hand portion text (max. 11 characters) */
	public $left_text;

	/** @var string Hex color code for the left-hand text */
	public $left_textcolor;

	/** @var string Hex color code for the left-hand background */
	public $left_bgcolor;

	/** @var string Text size of the left-hand text; either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px) */
	public $left_textsize;

	/** @var string Right-hand portion text (max. 70 characters) */
	public $right_text;

	/** @var string Hex color code for the right-hand text */
	public $right_textcolor;

	/** @var string Hex color code for the right-hand background */
	public $right_bgcolor;

	/** @var string Text size of the right-hand text; either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px) */
	public $right_textsize;

	/** @var string FanBox image name (if any) */
	public $fantag_image;

	/** @var array Categories the UserBox is in; assume normal getText format instead of DBKey format
	 * @note Currently NOT always set; primarly used by the rollback/undo/"editing old version" logic!
	 */
	 public $categories;

	/** @var bool */
	public $dataLoaded = false;

	/**
	 * Constructor
	 *
	 * @param Title $title
	 * @throws MWException on invalid Title
	 */
	public function __construct( $title ) {
		if ( !is_object( $title ) ) {
			throw new MWException( 'FanBox constructor given bogus title.' );
		}
		$this->title = $title;
		$this->name = $title->getDBkey();
	}

	/**
	 * Create a Fantag object from a fantag name
	 *
	 * @param string $name Name of the fanbox, used to create a title object
	 *                      using Title::makeTitleSafe
	 * @return FanBox|null New instance of FanBox for the constructed title or null on failure
	 */
	public static function newFromName( $name ) {
		$title = Title::makeTitleSafe( NS_FANTAG, $name );
		return $title ? new FanBox( $title ) : null;
	}

	/**
	 * Given a non-current revision identifier, creates a FanBox object from said revision's data.
	 *
	 * @param int $oldId
	 * @return FanBox|null New instance of FanBox for the constructed title or null on failure
	 */
	public static function newFromOldId( $oldId ) {
		$oldRevision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $oldId, 0 );
		$title = null;
		$oldText = '';

		if ( !$oldRevision ) {
			return null;
		}

		$oldContent = $oldRevision->getContent( MediaWiki\Revision\SlotRecord::MAIN );
		if ( !$oldContent ) {
			return null;
		}

		'@phan-var Content $oldContent';
		if ( method_exists( $oldContent, 'getText' ) ) {
			// MW 1.39+ (at least)
			// @phan-suppress-next-line PhanUndeclaredMethod It's...not supposed to be undeclared
			$oldText = $oldContent->getText();
		} else {
			$oldText = ContentHandler::getContentText( $oldContent );
		}

		$title = $oldRevision->getPage();

		// @phan-suppress-next-line PhanRedundantCondition I disagree.
		if ( $title && $oldText ) {
			// This probably needs a documentation update or something, because it's not wrong *per se*
			// as the code _does_ work and behaves exactly as it should.
			// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType
			return ( new FanBox( $title ) )->setVariablesFromText( $oldText )->setRevisionId( $oldId );
		} else {
			return null;
		}
	}

	/**
	 * @param int $revId Revision ID
	 * @return FanBox The current FanBox object to allow daisy-chaining
	 */
	public function setRevisionId( $revId ) {
		$this->revisionId = (int)$revId;
		return $this;
	}

	/**
	 * Given a revision text of a typical UserBox: page, extracts categories from
	 * it and loads them into the class member variable $this->categories.
	 *
	 * @param string $text Revision text
	 */
	public function getAndSetCategoriesFromText( $text ) {
		$text = preg_replace( '/<!--.*-->/is', '', $text );
		$text = str_replace( [
			'{{DEFAULTSORT:{{PAGENAME}}}}',
			'__NOEDITSECTION__'
		], '', $text );
		// There is one double-newline which we need to convert to a single newline to get a neat array
		$text = str_replace( "\n\n", "\n", $text );
		$text = trim( $text );

		$categories = explode( "\n", $text );

		// @phan-suppress-next-line PhanRedundantCondition Shut up, it's not guaranteed to have meaningful content
		if ( $categories ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();

			foreach ( $categories as $category ) {
				$category = trim( $category );

				// Remove wikitext, category NS name and potential newline from $category
				$category = str_replace( [ '[[' . $contLang->getNsText( NS_CATEGORY ) . ':', ']]', "\n" ], '', $category );

				$this->categories[] = $category;
			}
		}
	}

	/**
	 * Given the textual content of a UserBox: page, sets the FanBox class variables
	 * using said text.
	 *
	 * @param string $text Textual representation of a Content object
	 * @return FanBox The current FanBox object to allow daisy-chaining
	 */
	public function setVariablesFromText( $text ) {
		if ( !$text ) {
			throw new MWException( __METHOD__ . ' must not be called with an empty $text string!' );
		}

		// Since we know that a UserBox: page will always have a certain format (see FanBox#buildWikiText), we
		// can make certain predictions which'll be true.
		// A standard UserBox: page with one category will have up to <s>12</s> 13 (0-keyed, obviously) indexes
		// as of 13 June 2024. Indexes 9, 11 and 12 being always empty at this point.
		// But we only care about 0-8 since they contain the data we need for the fantag table...

		// Do this first in order to populate $this->categories for further use by Special:UserBoxes.
		// (Needed to edit old versions of UserBox: pages, either as-is or in order to rollback/undo them properly
		// *without* nuking the existing category data!)
		$this->getAndSetCategoriesFromText( $text );

		// But first, let's strip out the comment characters.
		$text = str_replace( [ '<!--', '-->' ], '', $text );

		// ...then let's turn it into an array...
		$textAsArray = explode( "\n", $text );

		// ...and now we have something we can actually use:
		$this->left_text = str_replace( 'left_text:', '', $textAsArray[0] ?? '' );
		$this->left_textcolor = str_replace( 'left_textcolor:', '', $textAsArray[1] ?? '' );
		$this->left_bgcolor = str_replace( 'left_bgcolor:', '', $textAsArray[2] ?? '' );
		$this->right_text = str_replace( 'right_text:', '', $textAsArray[3] ?? '' );
		$this->right_textcolor = str_replace( 'right_textcolor:', '', $textAsArray[4] ?? '' );
		$this->right_bgcolor = str_replace( 'right_bgcolor:', '', $textAsArray[5] ?? '' );
		$this->left_textsize = str_replace( 'left_textsize:', '', $textAsArray[6] ?? '' );
		$this->right_textsize = str_replace( 'right_textsize:', '', $textAsArray[7] ?? '' );
		$this->fantag_image = str_replace( 'image_name:', '', $textAsArray[8] ?? '' ); // NEW as of 13 June 2024

		$this->exists = true;
		// XXX Is $this->title actually set yet?!
		// Answer: maybe NOT for FanBoxPage (FIXME!!!) but it should be set for *this* class' use cases. Hopefully.
		if ( $this->title ) {
			$this->pg_id = $this->title->getArticleID();
		}

		// @todo FIXME?
		// 1) fantag ID
		// 2) actor ID
		// ^Neither can be derived from the wikitext as-is and both would require a separate DB query or other similar lookup!

		// Allow daisy-chaining
		return $this;
	}

	/**
	 * Insert info into fantag table in the database when user creates fantag
	 *
	 * @param string $fantag_left_text Left side text
	 * @param string $fantag_left_textcolor Left side text color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_left_bgcolor Left side background color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_right_text Right side text
	 * @param string $fantag_right_textcolor Right side text color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_right_bgcolor Right side background color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_image_name Image name [optional, used only if the user chose to upload an image to the left side]
	 * @param string $fantag_left_textsize Left side text size, either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px)
	 * @param string $fantag_right_textsize Right side text size, either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px)
	 * @param string $categories Categories as a comma-separated string
	 * @param User $user User creating the fantag
	 * @param string $summary Edit summary provided by the user, if any
	 * @return int|null
	 */
	public function addFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text,
		$fantag_right_textcolor, $fantag_right_bgcolor, $fantag_image_name,
		$fantag_left_textsize, $fantag_right_textsize, $categories, User $user,
		$summary = ''
	) {
		$dbw = wfGetDB( DB_PRIMARY );

		$descTitle = $this->getTitle();
		$desc = wfMessage( 'fanbox-summary-new' )->inContentLanguage()->parse();
		if ( $summary ) {
			$desc = $summary;
		}
		$article = new Article( $descTitle );
		$services = MediaWikiServices::getInstance();

		$categories_wiki = '';
		if ( $categories ) {
			$categories_a = explode( ',', $categories );
			$contLang = $services->getContentLanguage();
			foreach ( $categories_a as $category ) {
				$categories_wiki .= '[[' .
					$contLang->getNsText( NS_CATEGORY ) . ':' .
					trim( $category ) . "]]\n";
			}
		}
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = $services->getWikiPageFactory()->newFromTitle( $this->title );
		} else {
			// @phan-suppress-next-line PhanUndeclaredStaticMethod
			$page = WikiPage::factory( $this->title );
		}

		if ( $descTitle->exists() ) {
			# Invalidate the cache for the description page
			$descTitle->invalidateCache();

			$htmlCache = $services->getHtmlCacheUpdater();
			$htmlCache->purgeTitleUrls( $descTitle, $htmlCache::PURGE_INTENT_TXROUND_REFLECTED );
		} else {
			// Set these variables for buildWikiText(), which uses the accessor methods
			// @todo This feels kinda nasty...
			$this->left_text = $fantag_left_text;
			$this->left_textcolor = $fantag_left_textcolor;
			$this->left_bgcolor = $fantag_left_bgcolor;
			$this->left_textsize = $fantag_left_textsize;
			$this->right_text = $fantag_right_text;
			$this->right_textcolor = $fantag_right_textcolor;
			$this->right_bgcolor = $fantag_right_bgcolor;
			$this->right_textsize = $fantag_right_textsize;
			$this->fantag_image = $fantag_image_name;

			// New fantag; create the description page.
			$pageContent = ContentHandler::makeContent(
				$this->buildWikiText() . "\n\n" .
				$this->getBaseCategories( $user ) . "\n" . $categories_wiki .
				"\n__NOEDITSECTION__",
				$page->getTitle()
			);
			if ( method_exists( $page, 'doUserEditContent' ) ) {
				// MW 1.36+
				$page->doUserEditContent( $pageContent, $user, $desc );
			} else {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$page->doEditContent( $pageContent, $desc, /*$flags =*/ 0, /*$originalRevId =*/ false, $user );
			}
		}

		# Test to see if the row exists using INSERT IGNORE
		# This avoids race conditions by locking the row until the commit, and also
		# doesn't deadlock. SELECT FOR UPDATE causes a deadlock for every race condition.
		$dbw->insert(
			'fantag',
			[
				'fantag_title' => $this->getName(),
				'fantag_left_text' => $fantag_left_text,
				'fantag_left_textcolor' => $fantag_left_textcolor,
				'fantag_left_bgcolor' => $fantag_left_bgcolor,
				'fantag_right_text' => $fantag_right_text,
				'fantag_right_textcolor' => $fantag_right_textcolor,
				'fantag_right_bgcolor' => $fantag_right_bgcolor,
				'fantag_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) ),
				'fantag_pg_id' => $article->getPage()->getId(),
				'fantag_actor' => $user->getActorId(),
				'fantag_image_name' => $fantag_image_name,
				'fantag_left_textsize' => $fantag_left_textsize,
				'fantag_right_textsize' => $fantag_right_textsize,
			],
			__METHOD__,
			'IGNORE'
		);
		return $dbw->insertId();
	}

	/**
	 * Insert info into user_fantag table when user creates fantag or grabs it
	 *
	 * @param User $user
	 * @param int $userft_fantag_id Fantag ID number
	 */
	public function addUserFan( User $user, $userft_fantag_id ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->insert(
			'user_fantag',
			[
				'userft_fantag_id' => intval( $userft_fantag_id ),
				'userft_actor' => $user->getActorId(),
				'userft_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) ),
			],
			__METHOD__
		);
	}

	/**
	 * Output information about the fanbox as a HTML comment.
	 *
	 * This is displayed on UserBox: page diffs (so that users can easily see
	 * changes in hex colors and such).
	 *
	 * @return string
	 */
	public function buildWikiText() {
		$output = '';
		$output .= "left_text:{$this->getFanBoxLeftText()}\n";
		$output .= "left_textcolor:{$this->getFanBoxLeftTextColor()}\n";
		$output .= "left_bgcolor:{$this->getFanBoxLeftBgColor()}\n";
		$output .= "right_text:{$this->getFanBoxRightText()}\n";
		$output .= "right_textcolor:{$this->getFanBoxRightTextColor()}\n";
		$output .= "right_bgcolor:{$this->getFanBoxRightBgColor()}\n";
		$output .= "left_textsize:{$this->getFanBoxLeftTextSize()}\n";
		$output .= "right_textsize:{$this->getFanBoxRightTextSize()}\n";
		$output .= "image_name:{$this->getFanBoxImage()}\n";

		$output = "<!--{$output}-->";
		return $output;
	}

	/**
	 * Get base category string, i.e. DEFAULTSORT + "Userboxes by <user name>" category.
	 *
	 * @param User $user User object to use if the fanbox's creator cannot be loaded
	 * @return string Wikitext
	 */
	public function getBaseCategories( User $user ) {
		$creator = $this->getActor();
		if ( !$creator ) {
			$creator = $user->getName();
		} else {
			$creator = User::newFromActorId( $creator )->getName();
		}
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$ctg = '{{DEFAULTSORT:{{PAGENAME}}}}';
		$ctg .= '[[' . $contLang->getNsText( NS_CATEGORY ) . ':' .
			wfMessage( 'fanbox-userbox-category', $creator )->inContentLanguage()->parse() . "]]\n";
		return $ctg;
	}

	/**
	 * Update a fanbox.
	 *
	 * @param string $fantag_left_text Left side text
	 * @param string $fantag_left_textcolor Left side text color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_left_bgcolor Left side background color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_right_text Right side text
	 * @param string $fantag_right_textcolor Right side text color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_right_bgcolor Right side background color as a valid CSS hex code (incl. the # sign)
	 * @param string $fantag_image_name Image name [optional, used only if the user chose to upload an image to the left side]
	 * @param string $fantag_left_textsize Left side text size, either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px)
	 * @param string $fantag_right_textsize Right side text size, either "smallfont" (12px), "mediumfont" (14px) or "bigfont" (20px)
	 * @param int $fanboxId Internal identifier of the fanbox we're updating
	 * @param string|array $categories Categories either as a comma-separated string or as an array
	 * @param User|MediaWiki\User\UserIdentity $user User performing the update
	 * @param string $summary Edit summary provided by the user, if any
	 * @param bool $skipWikiPageUpdates Skip touching the UserBox: page and just do the "fantag" DB table updates + cache purge?
	 */
	public function updateFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text, $fantag_right_textcolor,
		$fantag_right_bgcolor, $fantag_image_name, $fantag_left_textsize,
		$fantag_right_textsize, $fanboxId, $categories, $user, $summary = '',
		$skipWikiPageUpdates = false
	) {
		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->update(
			'fantag',
			[
				'fantag_left_text' => $fantag_left_text,
				'fantag_left_textcolor' => $fantag_left_textcolor,
				'fantag_left_bgcolor' => $fantag_left_bgcolor,
				'fantag_right_text' => $fantag_right_text,
				'fantag_right_textcolor' => $fantag_right_textcolor,
				'fantag_right_bgcolor' => $fantag_right_bgcolor,
				'fantag_image_name' => $fantag_image_name,
				'fantag_left_textsize' => $fantag_left_textsize,
				'fantag_right_textsize' => $fantag_right_textsize,
			],
			[ 'fantag_pg_id' => intval( $fanboxId ) ],
			__METHOD__
		);

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'fantag', 'page', $this->name );
		$cache->delete( $key );

		if ( $skipWikiPageUpdates ) {
			return;
		}

		$categories_wiki = '';
		if ( $categories ) {
			if ( !is_array( $categories ) ) {
				$categories_a = explode( ',', $categories );
			} else {
				$categories_a = $categories;
			}
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			foreach ( $categories_a as $category ) {
				$categories_wiki .= '[[' . $contLang->getNsText( NS_CATEGORY ) .
					':' . trim( $category ) . "]]\n";
			}
		}
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		} else {
			// @phan-suppress-next-line PhanUndeclaredStaticMethod
			$page = WikiPage::factory( $this->title );
		}

		$pageContent = ContentHandler::makeContent(
			$this->buildWikiText() . "\n" .
			$this->getBaseCategories( $user ) . "\n" . $categories_wiki .
			"\n__NOEDITSECTION__",
			$page->getTitle()
		);

		if ( !$summary ) {
			$summary = wfMessage( 'fanbox-summary-update' )->inContentLanguage()->parse();
		}
		if ( method_exists( $page, 'doUserEditContent' ) ) {
			// MW 1.36+
			$page->doUserEditContent( $pageContent, $user, $summary );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$page->doEditContent( $pageContent, $summary );
		}
	}

	/**
	 * Remove fantag from user_fantag table when user removes it
	 *
	 * @param User $user
	 * @param int $userft_fantag_id Fantag ID number
	 */
	function removeUserFanBox( User $user, $userft_fantag_id ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'user_fantag',
			[
				'userft_actor' => $user->getActorId(),
				'userft_fantag_id' => intval( $userft_fantag_id )
			],
			__METHOD__
		);
	}

	/**
	 * Change count of fantag when user adds or removes it
	 *
	 * @param int $fanBoxId Fantag ID number
	 * @param int $number
	 */
	function changeCount( $fanBoxId, $number ) {
		$dbw = wfGetDB( DB_PRIMARY );

		$count = (int)$dbw->selectField(
			'fantag',
			'fantag_count',
			[ 'fantag_id' => $fanBoxId ],
			__METHOD__
		);

		$number = (int)$number;
		$dbw->update(
			'fantag',
			[ "fantag_count = {$count}+{$number}" ],
			[ 'fantag_id' => $fanBoxId ],
			__METHOD__
		);
	}

	/**
	 * Try to load fan metadata from memcached.
	 *
	 * @return bool true on success.
	 */
	private function loadFromCache() {
		$this->dataLoaded = false;

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'fantag', 'page', $this->name );
		$data = $cache->get( $key );

		if ( !empty( $data ) && is_array( $data ) ) {
			$this->id = $data['id'];
			$this->left_text = $data['lefttext'];
			$this->left_textcolor = $data['lefttextcolor'];
			$this->left_bgcolor = $data['leftbgcolor'];
			$this->right_text = $data['righttext'];
			$this->right_textcolor = $data['righttextcolor'];
			$this->right_bgcolor = $data['rightbgcolor'];
			$this->fantag_image = $data['fantagimage'];
			$this->left_textsize = $data['lefttextsize'];
			$this->right_textsize = $data['righttextsize'];
			$this->pg_id = $data['pgid'];
			$this->actor = $data['actor'];
			$this->dataLoaded = true;
			$this->exists = true;
		}

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		if ( $this->dataLoaded ) {
			wfDebug( "loaded Fan:{$this->name} from cache\n" );
			$stats->increment( 'fantag_cache_hit' );
		} else {
			$stats->increment( 'fantag_cache_miss' );
		}

		return $this->dataLoaded;
	}

	/**
	 * Save the fan data to memcached
	 */
	private function saveToCache() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'fantag', 'page', $this->name );
		if ( $this->exists() ) {
			$cachedValues = [
				'id' => $this->id,
				'lefttext' => $this->left_text,
				'lefttextcolor' => $this->left_textcolor,
				'leftbgcolor' => $this->left_bgcolor,
				'righttext' => $this->right_text,
				'righttextcolor' => $this->right_textcolor,
				'rightbgcolor' => $this->right_bgcolor,
				'fantagimage' => $this->fantag_image,
				'lefttextsize' => $this->left_textsize,
				'righttextsize' => $this->right_textsize,
				'actor' => $this->actor,
				'pgid' => $this->pg_id
			];
			$cache->set( $key, $cachedValues, 60 * 60 * 24 * 7 ); // A week
		} else {
			// However we should clear them, so they aren't leftover
			// if we've deleted the file.
			$cache->delete( $key );
		}
	}

	/**
	 * Populate class member variables directly from the database and set the flag
	 * indicating we've done that so we know not to hit the DB again.
	 */
	function loadFromDB() {
		$dbw = wfGetDB( DB_PRIMARY );

		// $this->revisionId is a revision.rev_id and it does NOT correspond to "oldid" in URL!
		if ( $this->revisionId ) {
			// Old version -> load from the text table
			$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $this->revisionId );

			if ( $rev ) {
				$content = $rev->getContent(
					MediaWiki\Revision\SlotRecord::MAIN,
					MediaWiki\Revision\RevisionRecord::FOR_PUBLIC /* or _RAW? not sure */
				);
				if ( $content === null ) {
					// PANIC!
					throw new MWException(
						'Got null revision content in ' . __METHOD__ . ' but that should never happen!'
					);
				}

				$this->setVariablesFromText( $content->serialize() );

				// @todo FIXME: Can we optimize this further?
				// Anyway, these properties of a FanBox object can't be read from the wikitext, so let's just
				// pull 'em from the DB:
				$r = $dbw->selectRow(
					'revision',
					[ 'rev_page', 'rev_actor' ],
					[ 'rev_id' => $this->revisionId ],
					__METHOD__
				);

				$pageId = (int)$r->rev_page;

				$this->id = (int)$dbw->selectField(
					'fantag',
					'fantag_id',
					[ 'fantag_pg_id' => $pageId ],
					__METHOD__
				);
				$this->pg_id = $pageId;
				$this->actor = (int)$r->rev_actor;
				$this->exists = true;

				/*
				USING ACCESSORS HERE RESULTS IN AN INFINITE LOOP!
				$this->id = $this->getFanBoxId();
				$this->left_text = $this->getFanBoxLeftText();
				$this->exists = true;
				$this->left_textcolor = $this->getFanBoxLeftTextColor();
				$this->left_bgcolor = $this->getFanBoxLeftBGColor();
				$this->right_text = $this->getFanBoxRightText();
				$this->right_textcolor = $this->getFanBoxRightTextColor();
				$this->right_bgcolor = $this->getFanBoxRightBGColor();
				$this->fantag_image = $this->getFanBoxImage();
				$this->left_textsize = $this->getFanBoxLeftTextSize();
				$this->right_textsize = $this->getFanBoxRightTextSize();
				*/
			}
		} else {
			$row = $dbw->selectRow(
				'fantag',
				[
					'fantag_id', 'fantag_left_text',
					'fantag_left_textcolor', 'fantag_left_bgcolor',
					'fantag_actor',
					'fantag_right_text', 'fantag_right_textcolor',
					'fantag_right_bgcolor', 'fantag_image_name',
					'fantag_left_textsize', 'fantag_right_textsize', 'fantag_pg_id'
				],
				[ 'fantag_title' => $this->name ],
				__METHOD__
			);

			if ( $row ) {
				$this->id = $row->fantag_id;
				$this->left_text = $row->fantag_left_text;
				$this->exists = true;
				$this->left_textcolor = $row->fantag_left_textcolor;
				$this->left_bgcolor = $row->fantag_left_bgcolor;
				$this->right_text = $row->fantag_right_text;
				$this->right_textcolor = $row->fantag_right_textcolor;
				$this->right_bgcolor = $row->fantag_right_bgcolor;
				$this->fantag_image = $row->fantag_image_name;
				$this->left_textsize = $row->fantag_left_textsize;
				$this->right_textsize = $row->fantag_right_textsize;
				$this->pg_id = $row->fantag_pg_id;
				$this->actor = $row->fantag_actor;
			}
		}

		# Unconditionally set loaded=true, we don't want the accessors constantly rechecking
		$this->dataLoaded = true;
	}

	/**
	 * Load fanbox metadata from cache or DB, unless already loaded
	 */
	function load() {
		if ( !$this->dataLoaded ) {
			if ( !$this->loadFromCache() ) {
				$this->loadFromDB();
				$this->saveToCache();
			}
			$this->dataLoaded = true;
		}
	}

	/**
	 * Output a fanbox's HTML.
	 *
	 * @return string
	 */
	public function outputFanBox() {
		global $wgOut;

		$services = MediaWikiServices::getInstance();
		$tagParser = $services->getParserFactory()->create();

		$fantag_image_tag = '';
		if ( $this->getFanBoxImage() ) {
			$fantag_image_width = 45;
			$fantag_image_height = 53;
			$fantag_image = $services->getRepoGroup()->findFile( $this->getFanBoxImage() );
			$fantag_image_url = '';
			if ( is_object( $fantag_image ) ) {
				$fantag_image_url = $fantag_image->createThumb(
					$fantag_image_width,
					$fantag_image_height
				);
			}
			$fantag_image_tag = '<img alt="" src="' . htmlspecialchars( $fantag_image_url ) . '"/>';
		}

		if ( $this->getFanBoxLeftText() == '' ) {
			$fantag_leftside = $fantag_image_tag;
		} else {
			$fantag_leftside = $this->getFanBoxLeftText();
			$fantag_leftside = $tagParser->parse(
				$fantag_leftside,
				$this->title,
				$wgOut->parserOptions(),
				false
			);
			$fantag_leftside = $fantag_leftside->getText();
		}

		$fantag_title = Title::makeTitle( NS_FANTAG, $this->name );
		$individual_fantag_id = $this->getFanBoxId();

		if ( $this->getFanBoxPageID() == $this->title->getArticleID() ) {
			$fantag_perma = '';
		} else {
			$fantag_perma = $services->getLinkRenderer()->makeLink(
				$fantag_title,
				wfMessage( 'fanbox-perma' )->text(),
				[
					'class' => 'perma',
					'style' => 'font-size:8px; color:' .
						$this->getFanBoxRightTextColor(),
					'title' => $this->name
				]
			);
		}

		$leftfontsize = '12px';
		if ( $this->getFanBoxLeftTextSize() == 'mediumfont' ) {
			$leftfontsize = '14px';
		}
		if ( $this->getFanBoxLeftTextSize() == 'bigfont' ) {
			$leftfontsize = '20px';
		}

		$rightfontsize = '14px';
		if ( $this->getFanBoxRightTextSize() == 'smallfont' ) {
			$rightfontsize = '12px';
		}
		if ( $this->getFanBoxRightTextSize() == 'mediumfont' ) {
			$rightfontsize = '14px';
		}

		$right_text = $this->getFanBoxRightText();
		$right_text = $tagParser->parse(
			$right_text, $this->title, $wgOut->parserOptions(), false
		);
		$right_text = $right_text->getText();

		$output = '<input type="hidden" name="individualFantagId" value="' . (int)$this->getFanBoxId() . '" />
			<div class="individual-fanbox" id="individualFanbox' . (int)$individual_fantag_id . "\">
				<div class=\"permalink-container\">
					$fantag_perma
				</div>
				<table class=\"fanBoxTable\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
					<tr>
						<td id=\"fanBoxLeftSideOutput\" style=\"color:" . htmlspecialchars( $this->getFanBoxLeftTextColor(), ENT_QUOTES ) . "; font-size:$leftfontsize\" bgcolor=\"" . htmlspecialchars( $this->getFanBoxLeftBgColor(), ENT_QUOTES ) . "\">" . $fantag_leftside . "</td>
						<td id=\"fanBoxRightSideOutput\" style=\"color:" . htmlspecialchars( $this->getFanBoxRightTextColor(), ENT_QUOTES ) . "; font-size:$rightfontsize\" bgcolor=\"" . htmlspecialchars( $this->getFanBoxRightBgColor(), ENT_QUOTES ) . "\">" . $right_text . "</td>
					</tr>
				</table>
			</div>";

		return $output;
	}

	/**
	 * Check if user has fanbox and output the right (add vs. remove) popup box
	 *
	 * @param User $user
	 * @return int
	 */
	public function checkIfUserHasFanBox( User $user ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$check_fanbox_count = $dbw->selectField(
			'user_fantag',
			'COUNT(*) AS count',
			[
				'userft_actor' => $user->getActorId(),
				'userft_fantag_id' => $this->getFanBoxId()
			],
			__METHOD__
		);

		return $check_fanbox_count;
	}

	/**
	 * Output some additional controls if a user has this fanbox.
	 *
	 * @return string
	 */
	public function outputIfUserHasFanBox() {
		$fanboxTitle = $this->getTitle();

		// Some healthy paranoia
		if ( !$fanboxTitle instanceof Title ) {
			return '';
		}

		$fanboxTitle = $fanboxTitle->getText();
		$individual_fantag_id = $this->getFanBoxId();

		$output = '
			<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . (int)$individual_fantag_id . '">
			<table cellpadding="0" cellspacing="0" width="258px">
				<tr>
					<td align="center">' .
						wfMessage( 'fanbox-remove-fanbox' )->escaped() .
					'</td>
				<tr>
					<td align="center">
					<input type="button" class="fanbox-remove-has-button" data-fanbox-title="' . $fanboxTitle . '" value="' . wfMessage( 'fanbox-remove' )->escaped() . '" size="20" />
					<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="20" />
				</td>
			</table>
			</div>';

		return $output;
	}

	/**
	 * Output the HTML allowing the user to add a fanbox to their profile if they
	 * already don't have it.
	 *
	 * @return string
	 */
	public function outputIfUserDoesntHaveFanBox() {
		$fanboxTitle = $this->getTitle();
		$fanboxTitle = $fanboxTitle->getText();
		$individual_fantag_id = $this->getFanBoxId();

		$output = '
			<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . (int)$individual_fantag_id . '">
			<table cellpadding="0" cellspacing="0" width="258px">
				<tr>
					<td align="center">' .
						wfMessage( 'fanbox-add-fanbox' )->escaped() .
					'</td>
				</tr>
				<tr>
					<td align="center">
						<input type="button" class="fanbox-add-doesnt-have-button" data-fanbox-title="' . $fanboxTitle . '" value="' . wfMessage( 'fanbox-add' )->escaped() . '" size="20" />
						<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="20" />
					</td>
				</tr>
			</table>
			</div>';

		return $output;
	}

	public function outputIfUserNotLoggedIn() {
		// The fantag ID in the div element is needed for the "login" popup
		// to work properly
		$individual_fantag_id = $this->getFanBoxId();
		$output = '<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $individual_fantag_id . '">
			<table cellpadding="0" cellspacing="0" width="258px">
				<tr>
					<td align="center">' .
						wfMessage( 'fanbox-add-fanbox-login' )->parse() .
					'</td>
				</tr>
				<tr>
					<td align="center">
						<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="20" />
					</td>
				</tr>
			</table>
			</div>';
		return $output;
	}

	/**
	 * Run the supplied $value through SpamRegex, both the $wg* global configuration variable
	 * and if installed, the anti-spam extension of the same name as well.
	 *
	 * @note Copied from ArticleFeedbackv5's ArticleFeedbackv5Utils class in July 2024 and amended w/
	 * the 2nd parameter.
	 *
	 * @param string $value
	 * @param string $summaryOrTextboxFilter Which SpamRegex (extension) filters to use? 'summary' for triggering
	 *   edit summary filtering, 'textbox' for textbox. Does nothing if the SpamRegex extension isn't installed, obviously.
	 * @return bool Will return boolean false if valid or true if flagged
	 */
	public static function validateSpamRegex( $value, $summaryOrTextboxFilter = 'textbox' ) {
		global $wgSpamRegex;

		// Apparently this has to use the name SpamRegex specifies in its extension.json
		// rather than the shorter directory name...
		$spamRegexExtIsInstalled = ExtensionRegistry::getInstance()->isLoaded( 'Regular Expression Spam Block' );

		// If and only if the config var is neither an array nor a string nor
		// do we have the extension installed, bail out then and *only* then.
		// It's entirely possible to have the extension installed without
		// the config var being explicitly changed from the default value.
		if (
			!(
				( is_array( $wgSpamRegex ) && count( $wgSpamRegex ) > 0 ) ||
				( is_string( $wgSpamRegex ) && strlen( $wgSpamRegex ) > 0 )
			) &&
			!$spamRegexExtIsInstalled
		) {
			return false;
		}

		// In older versions, $wgSpamRegex may be a single string rather than
		// an array of regexes, so make it compatible.
		$regexes = (array)$wgSpamRegex;

		// Support [[mw:Extension:SpamRegex]] if it's installed (T347215)
		if ( $spamRegexExtIsInstalled ) {
			// The following two lines have been changed, compared to the AFTv5/LinkFilter version of this method:
			$filterType = ( $summaryOrTextboxFilter === 'summary' ? SpamRegex::TYPE_SUMMARY : SpamRegex::TYPE_TEXTBOX );
			$phrases = SpamRegex::fetchRegexData( $filterType );
			if ( $phrases && is_array( $phrases ) ) {
				$regexes = array_merge( $regexes, $phrases );
			}
		}

		foreach ( $regexes as $regex ) {
			if ( preg_match( $regex, $value ) ) {
				// $value contains spam
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string The name of this fanbox
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Title The associated Title object
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return int The ID number of the fanbox
	 * @return-taint none
	 */
	public function getFanBoxId() {
		$this->load();
		return $this->id;
	}

	/**
	 * @return string Left-hand text of the fanbox
	 */
	public function getFanBoxLeftText() {
		$this->load();
		return $this->left_text;
	}

	/**
	 * @return string The color of the left-side text
	 */
	public function getFanBoxLeftTextColor() {
		$this->load();
		return $this->left_textcolor;
	}

	/**
	 * @return string Background color of the left side
	 */
	public function getFanBoxLeftBgColor() {
		$this->load();
		return $this->left_bgcolor;
	}

	/**
	 * @return string Right-hand text of the fanbox
	 */
	public function getFanBoxRightText() {
		$this->load();
		return $this->right_text;
	}

	/**
	 * @return string Text color of the right side text
	 */
	public function getFanBoxRightTextColor() {
		$this->load();
		return $this->right_textcolor;
	}

	/**
	 * @return string Background color of the right side
	 */
	public function getFanBoxRightBgColor() {
		$this->load();
		return $this->right_bgcolor;
	}

	/**
	 * @return string URL to the fanbox image (if any), I think
	 */
	public function getFanBoxImage() {
		$this->load();
		return $this->fantag_image;
	}

	/**
	 * @return string Size of the left-hand text
	 */
	public function getFanBoxLeftTextSize() {
		$this->load();
		return $this->left_textsize;
	}

	/**
	 * @return string Size of the right-hand text
	 */
	public function getFanBoxRightTextSize() {
		$this->load();
		return $this->right_textsize;
	}

	/**
	 * @return int Page ID for the current page
	 */
	public function getFanBoxPageID() {
		$this->load();
		return $this->pg_id;
	}

	/**
	 * @return int Actor ID of the user who created this FanBox
	 */
	public function getActor() {
		$this->load();
		return $this->actor;
	}

	/**
	 * @return bool True if the FanBox exists, else false
	 */
	public function exists() {
		$this->load();
		return $this->exists;
	}

	/**
	 * @return string The wikitext embed code for the current fanbox
	 */
	public function getEmbedThisCode() {
		$embedtitle = Title::makeTitle( NS_FANTAG, $this->getName() )->getPrefixedDBkey();
		return "[[$embedtitle]]";
	}

}
