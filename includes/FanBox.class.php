<?php
/**
 * FanBox class
 *
 * @file
 * @todo document
 */
class FanBox {

	public $name,
		$title,
		$exists,
		$create_date,
		$type,
		$fantag_id,
		$fantag_title,
		$user_name,
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
	 * @param $name String: name of the fanbox, used to create a title object
	 *                      using Title::makeTitleSafe
	 * @return Mixed new instance of FanBox for the constructed title or null
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
	 */
	public function addFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text,
		$fantag_right_textcolor, $fantag_right_bgcolor, $fantag_image_name,
		$fantag_left_textsize, $fantag_right_textsize, $categories
	) {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );

		$descTitle = $this->getTitle();
		$desc = wfMessage( 'fanbox-summary-new' )->inContentLanguage()->parse();
		$article = new Article( $descTitle );

		$categories_wiki = '';
		if ( $categories ) {
			$categories_a = explode( ',', $categories );
			$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
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
				$this->getBaseCategories() . "\n" . $categories_wiki .
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
				'fantag_date' => date( 'Y-m-d H:i:s' ),
				'fantag_pg_id' => $article->getID(),
				'fantag_user_id' => $wgUser->getID(),
				'fantag_user_name' => $wgUser->getName(),
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
	 * @param $userft_fantag_id Integer: fantag ID number
	 */
	public function addUserFan( $userft_fantag_id ) {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'user_fantag',
			[
				'userft_fantag_id' => intval( $userft_fantag_id ),
				'userft_user_id' => $wgUser->getID(),
				'userft_user_name' => $wgUser->getName(),
				'userft_date' => date( 'Y-m-d H:i:s' ),
			],
			__METHOD__
		);
	}

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

	public function getBaseCategories() {
		global $wgUser;
		$creator = $this->getUserName();
		if ( !$creator ) {
			$creator = $wgUser->getName();
		}
		$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
		$ctg = '{{DEFAULTSORT:{{PAGENAME}}}}';
		$ctg .= '[[' . $contLang->getNsText( NS_CATEGORY ) . ':' .
			wfMessage( 'fanbox-userbox-category' )->inContentLanguage()->parse() . "]]\n";
		return $ctg;
	}

	// Update fan
	public function updateFan( $fantag_left_text, $fantag_left_textcolor,
		$fantag_left_bgcolor, $fantag_right_text, $fantag_right_textcolor,
		$fantag_right_bgcolor, $fantag_image_name, $fantag_left_textsize,
		$fantag_right_textsize, $fanboxId, $categories
	) {
		global $wgMemc;

		$dbw = wfGetDB( DB_MASTER );

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
		$key = $wgMemc->makeKey( 'fantag', 'page', $this->name );
		$wgMemc->delete( $key );

		$categories_wiki = '';
		if ( $categories ) {
			$categories_a = explode( ',', $categories );
			$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
			foreach ( $categories_a as $category ) {
				$categories_wiki .= '[[' . $contLang->getNsText( NS_CATEGORY ) .
					':' . trim( $category ) . "]]\n";
			}
		}
		$page = WikiPage::factory( $this->title );

		$pageContent = ContentHandler::makeContent(
			$this->buildWikiText() . "\n" .
			$this->getBaseCategories() . "\n" . $categories_wiki .
			"\n__NOEDITSECTION__",
			$page->getTitle()
		);

		$page->doEditContent( $pageContent, wfMessage( 'fanbox-summary-update' )->inContentLanguage()->parse() );
	}

	/**
	 * Remove fantag from user_fantag table when user removes it
	 *
	 * @param $userft_fantag_id Integer: fantag ID number
	 */
	function removeUserFanBox( $userft_fantag_id ) {
		global $wgUser;
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'user_fantag',
			[
				'userft_user_name' => $wgUser->getName(),
				'userft_fantag_id' => intval( $userft_fantag_id )
			],
			__METHOD__
		);
	}

	/**
	 * Change count of fantag when user adds or removes it
	 *
	 * @param $fanBoxId Integer: fantag ID number
	 * @param $number Integer
	 */
	function changeCount( $fanBoxId, $number ) {
		$dbw = wfGetDB( DB_MASTER );

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
		global $wgMemc;

		$this->dataLoaded = false;

		$key = $wgMemc->makeKey( 'fantag', 'page', $this->name );
		$data = $wgMemc->get( $key );

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
			$this->user_id = $data['userid'];
			$this->user_name = $data['username'];
			$this->dataLoaded = true;
			$this->exists = true;
		}

		if ( $this->dataLoaded ) {
			wfDebug( "loaded Fan:{$this->name} from cache\n" );
			wfIncrStats( 'fantag_cache_hit' );
		} else {
			wfIncrStats( 'fantag_cache_miss' );
		}

		return $this->dataLoaded;
	}

	/**
	 * Save the fan data to memcached
	 */
	private function saveToCache() {
		global $wgMemc;

		$key = $wgMemc->makeKey( 'fantag', 'page', $this->name );
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
				'userid' => $this->user_id,
				'username' => $this->user_name,
				'pgid' => $this->pg_id
			];
			$wgMemc->set( $key, $cachedValues, 60 * 60 * 24 * 7 ); // A week
		} else {
			// However we should clear them, so they aren't leftover
			// if we've deleted the file.
			$wgMemc->delete( $key );
		}
	}

	function loadFromDB() {
		$dbw = wfGetDB( DB_MASTER );

		$row = $dbw->selectRow(
			'fantag',
			[
				'fantag_id', 'fantag_left_text',
				'fantag_left_textcolor', 'fantag_left_bgcolor',
				'fantag_user_id', 'fantag_user_name',
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
			$this->user_id = $row->fantag_user_id;
			$this->user_name = $row->fantag_user_name;
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

	public function outputFanBox() {
		global $wgOut;

		$tagParser = new Parser();

		if ( $this->getFanBoxImage() ) {
			$fantag_image_width = 45;
			$fantag_image_height = 53;
			$fantag_image = wfFindFile( $this->getFanBoxImage() );
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
			$fantag_perma = Linker::link(
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
	 */
	public function checkIfUserHasFanBox() {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );
		$check_fanbox_count = $dbw->selectField(
			'user_fantag',
			[ 'COUNT(*) AS count' ],
			[
				'userft_user_name' => $wgUser->getName(),
				'userft_fantag_id' => $this->getFanBoxId()
			],
			__METHOD__
		);

		return $check_fanbox_count;
	}

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
	 * @return String the name of this fanbox
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Object the associated Title object
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @return Integer the ID number of the fanbox
	 */
	public function getFanBoxId() {
		$this->load();
		return $this->id;
	}

	/**
	 * @return String left-hand text of the fanbox
	 */
	public function getFanBoxLeftText() {
		$this->load();
		return $this->left_text;
	}

	/**
	 * @return String the color of the left-side text
	 */
	public function getFanBoxLeftTextColor() {
		$this->load();
		return $this->left_textcolor;
	}

	/**
	 * @return String background color of the left side
	 */
	public function getFanBoxLeftBgColor() {
		$this->load();
		return $this->left_bgcolor;
	}

	/**
	 * @return String right-hand text of the fanbox
	 */
	public function getFanBoxRightText() {
		$this->load();
		return $this->right_text;
	}

	/**
	 * @return String text color of the right side text
	 */
	public function getFanBoxRightTextColor() {
		$this->load();
		return $this->right_textcolor;
	}

	/**
	 * @return String background color of the right side
	 */
	public function getFanBoxRightBgColor() {
		$this->load();
		return $this->right_bgcolor;
	}

	/**
	 * @return String URL to the fanbox image (if any), I think
	 */
	public function getFanBoxImage() {
		$this->load();
		return $this->fantag_image;
	}

	/**
	 * @return String size of the left-hand text
	 */
	public function getFanBoxLeftTextSize() {
		$this->load();
		return $this->left_textsize;
	}

	/**
	 * @return String size of the right-hand text
	 */
	public function getFanBoxRightTextSize() {
		$this->load();
		return $this->right_textsize;
	}

	/**
	 * @return Integer page ID for the current page
	 */
	public function getFanBoxPageID() {
		$this->load();
		return $this->pg_id;
	}

	/**
	 * @return Integer user ID of the user who created this FanBox
	 */
	public function getUserID() {
		$this->load();
		return $this->user_id;
	}

	/**
	 * @return String name of the user who created this FanBox
	 */
	public function getUserName() {
		$this->load();
		return $this->user_name;
	}

	/**
	 * @return Boolean true if the FanBox exists, else false
	 */
	public function exists() {
		$this->load();
		return $this->exists;
	}

	/**
	 * @return String the embed code for the current fanbox
	 */
	public function getEmbedThisCode() {
		$embedtitle = Title::makeTitle( NS_FANTAG, $this->getName() )->getPrefixedDBkey();
		return "[[$embedtitle]]";
	}

}
