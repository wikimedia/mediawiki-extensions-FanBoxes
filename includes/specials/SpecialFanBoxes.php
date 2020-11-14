<?php
/**
 * A special page for creating new social userboxes (a.k.a fanboxes a.k.a
 * fantags).
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class FanBoxes extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'UserBoxes', 'create-userbox' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Group this special page under the correct header on Special:SpecialPages.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Set it up so that you must be logged in to create a userbox
		if ( $user->getID() == 0 ) {
			$out->setPageTitle( $this->msg( 'fanbox-woops-title' )->plain() );
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $login->getFullURL( 'returnto=Special:UserBoxes' ) );
			return;
		}

		// Can the user execute the action?
		$this->checkPermissions();

		// If the database is in read-only mode, bail out
		$this->checkReadOnly();

		// Don't allow blocked users (RT #12589)
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Extension's CSS & JS
		$out->addModuleStyles( [
			'ext.fanBoxes.styles',
			'ext.fanBoxes.createform'
		] );
		$out->addModules( [
			'ext.fanBoxes.scripts',
			'ext.fanBoxes.colorpicker'
		] );

		// colorpicker
		$out->addScript( "<script type=\"text/javascript\" src=\"http://yui.yahooapis.com/2.5.2/build/utilities/utilities.js\"></script>\n" );
		$out->addScript( "<script type=\"text/javascript\" src=\"http://yui.yahooapis.com/2.5.2/build/slider/slider-min.js\"></script>\n" );
		$out->addScript( "<link rel=\"stylesheet\" type=\"text/css\" href=\"http://yui.yahooapis.com/2.5.2/build/colorpicker/assets/skins/sam/colorpicker.css\"/>\n" );
		$out->addScript( "<script type=\"text/javascript\" src=\"http://yui.yahooapis.com/2.5.2/build/colorpicker/colorpicker-min.js\"></script>\n" );

		$output = '';
		$title = str_replace( '#', '', $request->getVal( 'wpTitle' ) );
		$fanboxId = $request->getInt( 'id' );
		$categories = '';

		// Set up the edit fanbox part
		if ( $fanboxId ) {
			$title = Title::newFromID( $fanboxId );
			$update_fan = new FanBox( $title );

			// Get categories
			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select(
				'categorylinks',
				'cl_to',
				[ 'cl_from' => intval( $fanboxId ) ],
				__METHOD__
			);

			$fanboxCategory = $this->msg( 'fanbox-userbox-category' )->inContentLanguage()->parse();
			foreach ( $res as $row ) {
				if (
					$row->cl_to != $fanboxCategory &&
					// @todo FIXME: i18n
					strpos( $row->cl_to, 'Userboxes_by_User_' ) === false
				) {
					$categories .= ( ( $categories ) ? ', ' : '' ) . $row->cl_to;
				}
			}

			$output .= "
			<form action=\"\" method=\"post\" name=\"form1\">
			<input type=\"hidden\" name=\"fantag_image_name\" id=\"fantag_image_name\" value=\"{$update_fan->getFanBoxImage()}\">
			<input type=\"hidden\" name=\"textSizeRightSide\" id=\"textSizeRightSide\" value=\"{$update_fan->getFanBoxRightTextSize()}\" >
			<input type=\"hidden\" name=\"textSizeLeftSide\" id=\"textSizeLeftSide\" value=\"{$update_fan->getFanBoxLeftTextSize()}\">
			<input type=\"hidden\" name=\"bgColorLeftSideColor\" id=\"bgColorLeftSideColor\" value=\"{$update_fan->getFanBoxLeftBgColor()}\">
			<input type=\"hidden\" name=\"textColorLeftSideColor\" id=\"textColorLeftSideColor\" value=\"{$update_fan->getFanBoxLeftTextColor()}\">
			<input type=\"hidden\" name=\"bgColorRightSideColor\" id=\"bgColorRightSideColor\" value=\"{$update_fan->getFanBoxRightBgColor()}\">
			<input type=\"hidden\" name=\"textColorRightSideColor\" id=\"textColorRightSideColor\" value=\"{$update_fan->getFanBoxRightTextColor()}\">";

			if ( $update_fan->getFanBoxImage() ) {
				$fantag_image_width = 45;
				$fantag_image_height = 53;
				$fantag_image = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $update_fan->getFanBoxImage() );
				$fantag_image_url = '';
				if ( is_object( $fantag_image ) ) {
					$fantag_image_url = $fantag_image->createThumb(
						$fantag_image_width,
						$fantag_image_height
					);
				}
				$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '" />';
			}

			if ( $update_fan->getFanBoxLeftText() == '' ) {
				$fantag_leftside = $fantag_image_tag;
				$fantag_imageholder = $fantag_image_tag;
			} else {
				$fantag_leftside = $update_fan->getFanBoxLeftText();
				$fantag_imageholder = '';
			}

			$leftfontsize = $rightfontsize = '';
			if ( $update_fan->getFanBoxLeftTextSize() == 'mediumfont' ) {
				$leftfontsize = '14px';
			}
			if ( $update_fan->getFanBoxLeftTextSize() == 'bigfont' ) {
				$leftfontsize = '20px';
			}

			if ( $update_fan->getFanBoxRightTextSize() == 'smallfont' ) {
				$rightfontsize = '12px';
			}
			if ( $update_fan->getFanBoxRightTextSize() == 'mediumfont' ) {
				$rightfontsize = '14px';
			}

			$output .= "\n" . '<table class="fanBoxTable">
					<tr>
						<td id="fanBoxLeftSideContainer" style="background-color:' . $update_fan->getFanBoxLeftBgColor() . ';">
						<table>
							<tr>
							<td id="fanBoxLeftSideOutput2" style="color:' .
								$update_fan->getFanBoxLeftTextColor() .
								'; font-size:' . $leftfontsize . '">' .
								$fantag_leftside .
							'</td>
						</table>
						</td>
					<td id="fanBoxRightSideContainer" style="background-color:' . $update_fan->getFanBoxRightBgColor() . ';">
						<table>
							<tr>
							<td id="fanBoxRightSideOutput2" style="color:' .
								$update_fan->getFanBoxRightTextColor() .
								'; font-size:' . $rightfontsize . '">' .
									$update_fan->getFanBoxRightText() .
							'</td>
						</table>
					</td>
				</table>';

			$output .= '<h2 class="fanbox-form-label">' . $this->msg( 'fanbox-addtext' )->plain() . '</h2>
				<div class="create-fanbox-text">
					<div id="fanbox-left-text">
						<h3>' . $this->msg( 'fanbox-leftsidetext' )->plain() . '<span id="addImage">' .
							$this->msg( 'fanbox-display-image' )->plain() . '</span> <span id="closeImage">' .
							$this->msg( 'fanbox-close-image' )->plain() . "</span></h3>
						<input type=\"text\" name=\"inputLeftSide\" id=\"inputLeftSide\" value=\"{$update_fan->getFanBoxLeftText()}\" maxlength=\"11\"><br />
						<p>" . $this->msg( 'fanbox-leftsideinstructions' )->plain() . '</p>
					</div>
					<div id="fanbox-right-text">
						<h3>' . $this->msg( 'fanbox-rightsidetext' )->plain() . '<span class="fanbox-right-text-message">' . $this->msg( 'fanbox-charsleft', '<input readonly="readonly" type="text" name="countdown" value="70" /> ' )->text() . "</span></h3>
						<input type=\"text\" name=\"inputRightSide\" id=\"inputRightSide\" value=\"{$update_fan->getFanBoxRightText()}\" maxlength=\"70\" /><br />
						<p>" . $this->msg( 'fanbox-rightsideinstructions' )->plain() . '</p>
					</div>
				</div>
			</form>';

			$output .= '
					<div id="create-fanbox-image" class="create-fanbox-image">
						<h2 class="fanbox-form-label visualClear">' . $this->msg( 'fanbox-leftsideimage' )->plain() . '</h2>
						<p>' . $this->msg( 'fanbox-leftsideimageinstructions' )->plain() . " </p>
						<div id=\"fanbox_image\">$fantag_imageholder</div>
						<div id=\"fanbox_image2\"> </div>
						<div id=\"real-form\">
						<iframe id=\"imageUpload-frame\" class=\"imageUpload-frame\" width=\"700\"
							scrolling=\"no\" frameborder=\"0\" src=\"" .
							htmlspecialchars( SpecialPage::getTitleFor( 'FanBoxAjaxUpload' )->getFullURL() ) . '">
						</iframe>
						</div>
					</div>';

			$output .= $this->colorPickerAndCategoryCloud( $categories );

			$output .= '<div class="create-fanbox-buttons">
				<input type="button" class="site-button fanbox-simple-button" value="' .
					$this->msg( 'fanbox-update-button' )->plain() .
					'" size="20" />
			</div>';
		}

		// Set it up so that the page title includes the title of the red link that the user clicks on
		$destination = $request->getVal( 'destName' );
		$page_title = $this->msg( 'fanbox-addfan-title' )->plain();
		if ( $destination ) {
			$page_title = $this->msg( 'fanbox-createfor', str_replace( '_', ' ', $destination ) )->parse();
		}
		if ( $fanboxId ) {
			$page_title = $this->msg( 'fanbox-updatefan', str_replace( '_', ' ', $update_fan->getName() ) )->parse();
		}

		$out->setPageTitle( $page_title );

		// Set it up so that the title of the page the user creates using the create form ends
		// up being the title of the red link he clicked on to get to the create form
		if ( $destination ) {
			$title = $destination;
		}

		if ( !$fanboxId ) {
			$output .= '<div class="lr-right">' .
				$this->msg( 'userboxes-instructions' )->parse() .
			'</div>

			<form action="" method="post" name="form1">
			<input type="hidden" name="fantag_image_name" id="fantag_image_name" />
			<input type="hidden" name="fantag_imgname" id="fantag_imgname" />
			<input type="hidden" name="fantag_imgtag" id="fantag_imgtag" />
			<input type="hidden" name="textSizeRightSide" id="textSizeRightSide" />
			<input type="hidden" name="textSizeLeftSide" id="textSizeLeftSide" />
			<input type="hidden" name="bgColorLeftSideColor" id="bgColorLeftSideColor" value="" />
			<input type="hidden" name="textColorLeftSideColor" id="textColorLeftSideColor" value="" />
			<input type="hidden" name="bgColorRightSideColor" id="bgColorRightSideColor" value="" />
			<input type="hidden" name="textColorRightSideColor" id="textColorRightSideColor" value="" />';

			if ( !$destination ) {
				$output .= '<h2 class="fanbox-form-label">' . $this->msg( 'fanbox-title' )->plain() . '</h2>
					<div class="create-fanbox-title">
						<input type="text" name="wpTitle" id="wpTitle" value="' .
							$request->getVal( 'wpTitle' ) .
							'" style="width:350px" maxlength="60" /><br />
						<font size="1">(' . $this->msg( 'fanbox-maxchars-sixty' )->plain() . ')</font><br />
					</div>';
			} else {
				$output .= Html::hidden( 'wpTitle', $destination, [ 'id' => 'wpTitle' ] );
			}

			$output .= '<table class="fanBoxTable">
				<tr>
				<td id="fanBoxLeftSideContainer">
					<table>
						<tr>
							<td id="fanBoxLeftSideOutput2"></td>
						</tr>
					</table>
				</td>
				<td id="fanBoxRightSideContainer">
					<table style="width: 212px; height: 63px;">
						<tr>
							<td id="fanBoxRightSideOutput2"></td>
						</tr>
					</table>
				</td>
				</tr>
			</table>' . "\n";

			$output .= '<h2 class="fanbox-form-label">' . $this->msg( 'fanbox-addtext' )->plain() . '</h2>
				<div class="create-fanbox-text">
					<div id="fanbox-left-text">
						<h3>' . $this->msg( 'fanbox-leftsidetext' )->plain() .
							'<span id="addImage">' . $this->msg( 'fanbox-display-image' )->plain() .
							'</span> <span id="closeImage">' . $this->msg( 'fanbox-close-image' )->plain() . '</span></h3>
						<input type="text" name="inputLeftSide" id="inputLeftSide" maxlength="11" /><br />
						<font size="1">' . $this->msg( 'fanbox-leftsideinstructions' )->inContentLanguage()->parse() . '</font>
					</div>
					<div id="fanbox-right-text">
					<h3>' . $this->msg( 'fanbox-rightsidetext' )->inContentLanguage()->parse() . '<span id="countdownbox"> <span class="fanbox-right-text-message">'
						. $this->msg( 'fanbox-charsleft', '<input readonly="readonly" type="text" name="countdown" value="70" />' )->text() . '</span></span></h3>
						<input type="text" name="inputRightSide" id="inputRightSide" maxlength="70" /><br />
						<font size="1">' . $this->msg( 'fanbox-rightsideinstructions' ) . '</font>
					</div>
					<div class="visualClear"></div>
					</form>
				</div>';

			$output .= '<div id="create-fanbox-image" class="create-fanbox-image">
						<h2 class="fanbox-form-label">' . $this->msg( 'fanbox-leftsideimage' )->plain() .
							' <font size="1">' .
							$this->msg( 'fanbox-leftsideimageinstructions' )->inContentLanguage()->parse() .
							' </font></h2>
						<div id="fanbox_image"></div>
						<div id="fanbox_image2"></div>

						<div id="real-form">
						<iframe id="imageUpload-frame" class="imageUpload-frame" width="700"
							scrolling="no" frameborder="0" src="' . htmlspecialchars( SpecialPage::getTitleFor( 'FanBoxAjaxUpload' )->getFullURL() ) . '">
						</iframe>
						</div>
					</div>';

			$output .= $this->colorPickerAndCategoryCloud( $categories );

			$output .= '<div class="create-fanbox-buttons">
				<input type="button" class="site-button" value="' . $this->msg( 'fanbox-create-button' )->plain() . '" size="20" />
			</div>';
		}

		$out->addHTML( $output );

		// Send values to database and create fantag page when form is submitted
		if ( $request->wasPosted() ) {
			if ( !$fanboxId ) {
				$fan = FanBox::newFromName( $title );
				$fantagId = $fan->addFan(
					$request->getVal( 'inputLeftSide' ),
					$request->getVal( 'textColorLeftSideColor' ),
					$request->getVal( 'bgColorLeftSideColor' ),
					$request->getVal( 'inputRightSide' ),
					$request->getVal( 'textColorRightSideColor' ),
					$request->getVal( 'bgColorRightSideColor' ),
					$request->getVal( 'fantag_image_name' ),
					$request->getVal( 'textSizeLeftSide' ),
					$request->getVal( 'textSizeRightSide' ),
					$request->getVal( 'pageCtg' ),
					$user
				);
				$fan->addUserFan( $user, $fantagId );
				$out->redirect( $fan->title->getFullURL() );
			}
			if ( $fanboxId ) {
				$title = Title::newFromID( $fanboxId );
				$update_fan = new FanBox( $title );
				$update_fan->updateFan(
					$request->getVal( 'inputLeftSide' ),
					$request->getVal( 'textColorLeftSideColor' ),
					$request->getVal( 'bgColorLeftSideColor' ),
					$request->getVal( 'inputRightSide' ),
					$request->getVal( 'textColorRightSideColor' ),
					$request->getVal( 'bgColorRightSideColor' ),
					$request->getVal( 'fantag_image_name' ),
					$request->getVal( 'textSizeLeftSide' ),
					$request->getVal( 'textSizeRightSide' ),
					$fanboxId,
					$request->getVal( 'pageCtg' ),
					$user
				);
				$out->redirect( $update_fan->title->getFullURL() );
			}
		}
	}

	/**
	 * Return the HTML for the color picker and the category cloud.
	 *
	 * @param string $categories
	 * @return string
	 */
	function colorPickerAndCategoryCloud( $categories ) {
		$output = '<div class="add-colors">
					<h2 class="fanbox-form-label visualClear">' . $this->msg( 'fanbox-add-colors' )->plain() . '</h2>
					<div id="add-colors-left">
						<form name="colorpickerradio" action="">
						<input type="radio" name="colorpickerchoice" value="leftBG" checked="checked" />' .
							$this->msg( 'fanbox-leftbg-color' )->plain() .
						'<br />
						<input type="radio" name="colorpickerchoice" value="leftText" />' .
							$this->msg( 'fanbox-lefttext-color' )->plain() .
						'<br />
						<input type="radio" name="colorpickerchoice" value="rightBG" />' .
							$this->msg( 'fanbox-rightbg-color' )->plain() .
						'<br />
						<input type="radio" name="colorpickerchoice" value="rightText" />' .
						$this->msg( 'fanbox-righttext-color' )->plain() . '
						</form>
					</div>

					<div id="add-colors-right">
					<div id="colorpickerholder"></div>
					</div>

					<div class="visualClear"></div>
				</div>';

		// Category cloud stuff
		$cloud = new TagCloud( 10 );
		$categoriesLabel = $this->msg( 'fanbox-categories-label' )->plain();
		$categoriesHelpText = $this->msg( 'fanbox-categories-help' )->parse();

		$output .= '<div class="category-section">';
		$tagcloud = '<div id="create-tagcloud">';
		$tagnumber = 0;
		$tabcounter = 1;
		if ( isset( $cloud->tags ) && $cloud->tags ) {
			foreach ( $cloud->tags as $tag => $att ) {
				$tag = str_replace( 'Fans', '', $tag );
				$tag = trim( $tag );
				$slashedTag = $tag; // define variable
				// Fix for categories that contain an apostrophe
				if ( strpos( $tag, "'" ) ) {
					$slashedTag = str_replace( "'", "\'", $tag );
				}
				$tagcloud .= " <span id=\"tag-{$tagnumber}\" style=\"font-size:{$cloud->tags[$tag]['size']}{$cloud->tags_size_type}\">
				<a data-slashed-tag=\"{$slashedTag}\">{$tag}</a>
			</span>\n";
				$tagnumber++;
			}
		}

		$tagcloud .= '</div>';
		$output .= '<div class="create-category-title">';
		$output .= '<h2 class="fanbox-form-label">' . $categoriesLabel . '</h2>';
		$output .= '</div>';
		$output .= '<div class="categorytext">' . $categoriesHelpText . '</div>';
		$output .= $tagcloud;
		$output .= '<textarea class="createbox" tabindex="' . $tabcounter . '" accesskey="," name="pageCtg" id="pageCtg" rows="2" cols="80">' .
			$categories . '</textarea><br /><br />';
		$output .= '</div>';

		return $output;
	}
}
