<?php
/**
 * A special page for viewing the fanboxes of a user, either someone else or
 * yourself.
 *
 * @file
 * @ingroup Extensions
 */
class ViewFanBoxes extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'ViewUserBoxes' );
	}

	/**
	 * Group this special page under the correct header on Special:SpecialPages.
	 *
	 * @return String
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$currentUser = $this->getUser();

		$user_name = $request->getVal( 'user', $par );

		// Redirect Non-logged in users to Login Page
		if ( $currentUser->getID() == 0 && $user_name == '' ) {
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( htmlspecialchars( $login->getFullURL( 'returnto=Special:ViewUserBoxes' ) ) );
			return false;
		}

		$tagParser = new Parser();

		// Add CSS & JS
		$out->addModules( 'ext.fanBoxes' );

		$out->setPageTitle( $this->msg( 'fanbox-nav-header' )->plain() );

		// Code for viewing fanboxes for each user
		$output = '';
		$page = $request->getInt( 'page', 1 );

		// If no user is set in the URL, we assume it's the current user
		if ( !$user_name ) {
			$user_name = $currentUser->getName();
		}
		$user_id = User::idFromName( $user_name );
		$user = Title::makeTitle( NS_USER, $user_name );

		// Error message for username that does not exist (from URL)
		if ( $user_id == 0 ) {
			$out->setPageTitle( $this->msg( 'fanbox-woops' )->plain() );
			$out->addHTML( $this->msg( 'fanbox-userdoesnotexist' )->plain() );
			return false;
		}

		// Config for the page
		$per_page = 30;

		// Get all FanBoxes for this user into the array
		// Calls the FanBoxesClass file
		$userfan = new UserFanBoxes( $user_name );
		$userFanboxes = $userfan->getUserFanboxes( 0, $per_page, $page );
		$total = $userfan->getFanBoxCountByUsername( $user_name );
		$per_row = 3;

		// Page title and top part
		$out->setPageTitle( $this->msg( 'f-list-title', $userfan->user_name )->parse() );
		$output .= '<div class="back-links">
				<a href="' . $user->getFullURL() . '">' .
					$this->msg( 'f-back-link', $userfan->user_name )->parse() .
				'</a>
			</div>
			<div class="fanbox-count">' .
				$this->msg( 'f-count', $userfan->user_name, $total )->parse() .
			'</div>

			<div class="view-fanboxes-container clearfix">';

		if ( $userFanboxes ) {
			$x = 1;

			foreach ( $userFanboxes as $userfanbox ) {
				$check_user_fanbox = $userfan->checkIfUserHasFanbox( $userfanbox['fantag_id'] );

				if ( $userfanbox['fantag_image_name'] ) {
					$fantag_image_width = 45;
					$fantag_image_height = 53;
					$fantag_image = wfFindFile( $userfanbox['fantag_image_name'] );
					$fantag_image_url = '';
					if ( is_object( $fantag_image ) ) {
						$fantag_image_url = $fantag_image->createThumb(
							$fantag_image_width,
							$fantag_image_height
						);
					}
					$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '" />';
				}

				if ( $userfanbox['fantag_left_text'] == '' ) {
					$fantag_leftside = $fantag_image_tag;
				} else {
					$fantag_leftside = $userfanbox['fantag_left_text'];
					$fantag_leftside = $tagParser->parse(
						$fantag_leftside, $this->getPageTitle(),
						$out->parserOptions(), false
					);
					$fantag_leftside = $fantag_leftside->getText();
				}

				$leftfontsize = '12px';
				if ( $userfanbox['fantag_left_textsize'] == 'mediumfont' ) {
					$leftfontsize = '14px';
				}
				if ( $userfanbox['fantag_left_textsize'] == 'bigfont' ) {
					$leftfontsize = '20px';
				}

				$rightfontsize = '10px';
				if ( $userfanbox['fantag_right_textsize'] == 'smallfont' ) {
					$rightfontsize = '12px';
				}
				if ( $userfanbox['fantag_right_textsize'] == 'mediumfont' ) {
					$rightfontsize = '14px';
				}

				// Get permalink
				$fantag_title = Title::makeTitle( NS_FANTAG, $userfanbox['fantag_title'] );

				$right_text = $userfanbox['fantag_right_text'];
				$right_text = $tagParser->parse(
					$right_text,
					$this->getPageTitle(),
					$out->parserOptions(),
					false
				);
				$right_text = $right_text->getText();

				// Output fanboxes
				$output .= '<span class="top-fanbox">
				<div class="fanbox-item">
				<div class="individual-fanboxtest" id="individualFanbox' . $userfanbox['fantag_id'] . '">
					<div class="show-message-container" id="show-message-container' . $userfanbox['fantag_id'] . '">
						<div class="permalink-container">
							<a class="perma" style="font-size:8px; color:' . $userfanbox['fantag_right_textcolor'] . '" href="' . htmlspecialchars( $fantag_title->getFullURL() ) . "\" title=\"{$userfanbox['fantag_title']}\">" . $this->msg( 'fanbox-perma' )->plain() . "</a>
							<table class=\"fanBoxTable\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
								<tr>
									<td id=\"fanBoxLeftSideOutput\" style=\"color:" . $userfanbox['fantag_left_textcolor'] . "; font-size:$leftfontsize\" bgcolor=\"" . $userfanbox['fantag_left_bgcolor'] . '">' . $fantag_leftside . "</td>
									<td id=\"fanBoxRightSideOutput\" style=\"color:" . $userfanbox['fantag_right_textcolor'] . "; font-size:$rightfontsize\" bgcolor=\"" . $userfanbox['fantag_right_bgcolor'] . '">' . $right_text . '</td>
								</tr>
							</table>
						</div>
					</div>
				</div>';

				if ( $currentUser->isLoggedIn() ) {
					if ( $check_user_fanbox == 0 ) {
						$output .= '
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $userfanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0" width="258px">
						<tr>
							<td align="center">' .
								$this->msg( 'fanbox-add-fanbox' )->plain() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-add-button" value="' . $this->msg( 'fanbox-add' )->plain() . '" size="20" />
								<input type="button" class="fanbox-cancel-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" />
							</td>
						</tr>
					</table>
					</div>';
					} else {
						$output .= '
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $userfanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0" width="258px">
						<tr>
							<td align="center">' .
								$this->msg( 'fanbox-remove-fanbox' )->plain() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-remove-button" value="' . $this->msg( 'fanbox-remove' )->plain() . '" size="20" />
								<input type="button" class="fanbox-cancel-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" />
							</td>
						</tr>
					</table>
					</div>';
					}
				}

				if ( $currentUser->getID() == 0 ) {
					$output .= '<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $userfanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0" width="258px">
						<tr>
							<td align="center">' .
								$this->msg( 'fanbox-add-fanbox-login' )->parse() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-cancel-button" value="' . $this->msg( 'cancel' )->plain() . "\" size=\"20\" />
							</td>
						</tr>
					</table>
				</div>";
				}

				$output .= '</div></span>';

				if ( $x == count( $userFanboxes ) || $x != 1 && $x % $per_row == 0 ) {
					$output .= '<div class="visualClear"></div>';
				}
				$x++;
			}
		}

		$output .= '</div>';

		// Build next/prev nav
		$numofpages = $total / $per_page;

		$pageLink = $this->getPageTitle();
		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';

			if ( $page > 1 ) {
				$output .= Linker::link(
					$pageLink,
					$this->msg( 'fanbox-prev' )->plain(),
					[],
					[
						'user' => $user_name,
						'page' => ( $page - 1 )
					]
				) . $this->msg( 'word-separator' )->plain();
			}

			if ( ( $total % $per_page ) != 0 ) {
				$numofpages++;
			}

			if ( $numofpages >= 9 && $page < $total ) {
				$numofpages = 9 + $page;
			}

			if ( $numofpages >= ( $total / $per_page ) ) {
				$numofpages = ( $total / $per_page ) + 1;
			}

			for ( $i = 1; $i <= $numofpages; $i++ ) {
				if ( $i == $page ) {
					$output .= ( $i . ' ' );
				} else {
					$output .= Linker::link(
						$pageLink,
						$i,
						[],
						[
							'user' => $user_name,
							'page' => $i
						]
					) . $this->msg( 'word-separator' )->plain();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->plain() .
					Linker::link(
						$pageLink,
						$this->msg( 'fanbox-next' )->plain(),
						[],
						[
							'user' => $user_name,
							'page' => ( $page + 1 )
						]
					);
			}

			$output .= '</div>';
		}

		// Output everything
		$out->addHTML( $output );
	}

}