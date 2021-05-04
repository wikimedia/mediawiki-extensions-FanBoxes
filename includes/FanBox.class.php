<?php
/**
 * FanBox class - utilities for creating and managing fanboxes
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class FanBox {

	public $name,
		$title,
		$exists,
		$create_date,
		$type,
		$fantag_id,
		$fantag_title,
		$actor,
		$left_text,
		$left_textcolor,
		$left_bgcolor,
		$left_textsize,
		$right_text,
		$right_textcolor,
		$right_bgcolor,
		$right_textsize,
		$fantag_left_text,
		$fantag_left_textcolor,
		$fantag_left_bgcolor,
		$fantag_right_text,
		$fantag_right_textcolor,
		$fantag_right_bgcolor,
		$fantag_image_name,
		$fantag_left_textsize,
		$fantag_right_textsize,
		$userft_fantag_id;

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
		$this->title =& $title;
		$this->name = $title->getDBkey();
		$this->dataLoaded = false;
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

		if ( is_object( $title ) ) {
			return new FanBox( $title );
		} else {
			return null;
		}
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
	 * @return int|null
	 */
	public function addFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text,
		$fantag_right_textcolor, $fantag_right_bgcolor, $fantag_image_name,
		$fantag_left_textsize, $fantag_right_textsize, $categories, User $user
	) {
		$dbw = wfGetDB( DB_PRIMARY );

		$descTitle = $this->getTitle();
		$desc = wfMessage( 'fanbox-summary-new' )->inContentLanguage()->parse();
		$article = new Article( $descTitle );

		$categories_wiki = '';
		if ( $categories ) {
			$categories_a = explode( ',', $categories );
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			foreach ( $categories_a as $category ) {
				$categories_wiki .= '[[' .
					$contLang->getNsText( NS_CATEGORY ) . ':' .
					trim( $category ) . "]]\n";
			}
		}
		$page = WikiPage::factory( $this->title );

		if ( $descTitle->exists() ) {
			# Invalidate the cache for the description page
			$descTitle->invalidateCache();
			$descTitle->purgeSquid();
		} else {
			// New fantag; create the description page.
			$pageContent = ContentHandler::makeContent(
				$this->buildWikiText() . "\n\n" .
				$this->getBaseCategories( $user ) . "\n" . $categories_wiki .
				"\n__NOEDITSECTION__",
				$page->getTitle()
			);
			$page->doEditContent( $pageContent, $desc );
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
				'fantag_pg_id' => $article->getID(),
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
	 * @param string $categories Categories as a comma-separated string
	 * @param User $user User performing the update
	 */
	public function updateFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text, $fantag_right_textcolor,
		$fantag_right_bgcolor, $fantag_image_name, $fantag_left_textsize,
		$fantag_right_textsize, $fanboxId, $categories, User $user
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

		$categories_wiki = '';
		if ( $categories ) {
			$categories_a = explode( ',', $categories );
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			foreach ( $categories_a as $category ) {
				$categories_wiki .= '[[' . $contLang->getNsText( NS_CATEGORY ) .
					':' . trim( $category ) . "]]\n";
			}
		}
		$page = WikiPage::factory( $this->title );

		$pageContent = ContentHandler::makeContent(
			$this->buildWikiText() . "\n" .
			$this->getBaseCategories( $user ) . "\n" . $categories_wiki .
			"\n__NOEDITSECTION__",
			$page->getTitle()
		);

		$page->doEditContent( $pageContent, wfMessage( 'fanbox-summary-update' )->inContentLanguage()->parse() );
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

		$count = $dbw->selectField(
			'fantag',
			'fantag_count',
			[ 'fantag_id' => $fanBoxId ],
			__METHOD__
		);

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
			$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '"/>';
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
				wfMessage( 'fanbox-perma' )->plain(),
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

		$output = '<input type="hidden" name="individualFantagId" value="' . $this->getFanBoxId() . '" />
			<div class="individual-fanbox" id="individualFanbox' . $individual_fantag_id . "\">
				<div class=\"permalink-container\">
					$fantag_perma
				</div>
				<table class=\"fanBoxTable\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
					<tr>
						<td id=\"fanBoxLeftSideOutput\" style=\"color:" . $this->getFanBoxLeftTextColor() . "; font-size:$leftfontsize\" bgcolor=\"" . $this->getFanBoxLeftBgColor() . "\">" . $fantag_leftside . "</td>
						<td id=\"fanBoxRightSideOutput\" style=\"color:" . $this->getFanBoxRightTextColor() . "; font-size:$rightfontsize\" bgcolor=\"" . $this->getFanBoxRightBgColor() . "\">" . $right_text . "</td>
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
			[ 'COUNT(*) AS count' ],
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
			<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $individual_fantag_id . '">
			<table cellpadding="0" cellspacing="0" width="258px">
				<tr>
					<td align="center">' .
						wfMessage( 'fanbox-remove-fanbox' )->plain() .
					'</td>
				<tr>
					<td align="center">
					<input type="button" class="fanbox-remove-has-button" data-fanbox-title="' . $fanboxTitle . '" value="' . wfMessage( 'fanbox-remove' )->plain() . '" size="20" />
					<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="20" />
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
			<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $individual_fantag_id . '">
			<table cellpadding="0" cellspacing="0" width="258px">
				<tr>
					<td align="center">' .
						wfMessage( 'fanbox-add-fanbox' )->plain() .
					'</td>
				</tr>
				<tr>
					<td align="center">
						<input type="button" class="fanbox-add-doesnt-have-button" data-fanbox-title="' . $fanboxTitle . '" value="' . wfMessage( 'fanbox-add' )->plain() . '" size="20" />
						<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="20" />
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
						<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="20" />
					</td>
				</tr>
			</table>
			</div>';
		return $output;
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
