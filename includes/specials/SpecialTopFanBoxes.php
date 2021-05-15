<?php
/**
 * A special page to show either the most popular userboxes (default) or
 * alternatively, the newest userboxes.
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class TopFanBoxes extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'TopUserboxes' );
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

		// Add CSS & JS
		$out->addModuleStyles( 'ext.fanBoxes.styles' );
		$out->addModules( 'ext.fanBoxes.scripts' );

		$topfanboxId = $request->getVal( 'id' );
		$topfanboxCategory = $request->getVal( 'cat' );

		if ( $topfanboxId == 'fantag_date' ) {
			$out->setPageTitle( $this->msg( 'fanbox-most-recent-fanboxes-link' )->plain() );
			$topfanboxes = $this->getTopFanboxes( 'fantag_date' );
		} else {
			$out->setPageTitle( $this->msg( 'topuserboxes' )->plain() );
			$topfanboxes = $this->getTopFanboxes( 'fantag_count' );
		}

		$output = '';

		// Make top right navigation bar
		$output .= '<div class="fanbox-nav">
			<h2>' . $this->msg( 'fanbox-nav-header' )->plain() . '</h2>
			<p><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL() ) . '">' .
				$this->msg( 'fanbox-top-fanboxes-link' )->plain() . '</a></p>
			<p><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'id=fantag_date' ) ) . '">' .
				$this->msg( 'fanbox-most-recent-fanboxes-link' )->plain() . '</a></p>
		</div>';

		// Nothing? That means that no userboxes have been created yet...so
		// show a message to the user about that, prompting them to create some
		// userboxes
		if ( empty( $topfanboxes ) ) {
			$output .= $this->msg( 'fanbox-top-list-is-empty' )->parse();
		}
		$services = MediaWikiServices::getInstance();
		$repoGroup = $services->getRepoGroup();

		if ( !$topfanboxCategory ) {
			$x = 1;

			$output .= '<div class="top-fanboxes">';

			$tagParser = $services->getParserFactory()->create();

			foreach ( $topfanboxes as $topfanbox ) {
				$check_user_fanbox = $this->checkIfUserHasFanbox( $topfanbox['fantag_id'] );

				if ( $topfanbox['fantag_image_name'] ) {
					$fantag_image_width = 45;
					$fantag_image_height = 53;
					$fantag_image = $repoGroup->findFile( $topfanbox['fantag_image_name'] );
					$fantag_image_url = '';
					if ( is_object( $fantag_image ) ) {
						$fantag_image_url = $fantag_image->createThumb(
							$fantag_image_width,
							$fantag_image_height
						);
					}
					$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '"/>';
				}

				if ( $topfanbox['fantag_left_text'] == '' ) {
					$fantag_leftside = $fantag_image_tag;
				} else {
					$fantag_leftside = $topfanbox['fantag_left_text'];
					$fantag_leftside = $tagParser->parse(
						$fantag_leftside, $this->getPageTitle(),
						$out->parserOptions(), false
					);
					$fantag_leftside = $fantag_leftside->getText();
				}

				if ( $topfanbox['fantag_left_textsize'] == 'mediumfont' ) {
					$leftfontsize = '14px';
				}
				if ( $topfanbox['fantag_left_textsize'] == 'bigfont' ) {
					$leftfontsize = '20px';
				}

				if ( $topfanbox['fantag_right_textsize'] == 'smallfont' ) {
					$rightfontsize = '12px';
				}
				if ( $topfanbox['fantag_right_textsize'] == 'mediumfont' ) {
					$rightfontsize = '14px';
				}

				// Get permalink
				$fantag_title = Title::makeTitle( NS_FANTAG, $topfanbox['fantag_title'] );

				// Get creator
				$creator = User::newFromActorId( $topfanbox['fantag_actor'] );
				$userftusername = $creator->getName();
				$userftuserid = $creator->getId();
				$user_title = Title::makeTitle( NS_USER, $userftusername );
				$avatar = new wAvatar( $userftuserid, 'm' );

				$right_text = $topfanbox['fantag_right_text'];
				$right_text = $tagParser->parse(
					$right_text, $this->getPageTitle(), $out->parserOptions(), false
				);
				$right_text = $right_text->getText();

				$output .= "
				<div class=\"top-fanbox-row\">
				<span class=\"top-fanbox-num\">{$x}.</span><span class=\"top-fanbox\">

				<div class=\"fanbox-item\">

				<div class=\"individual-fanbox\" id=\"individualFanbox" . $topfanbox['fantag_id'] . "\">
					<div class=\"show-message-container\" id=\"show-message-container" . $topfanbox['fantag_id'] . "\">
						<div class=\"permalink-container\">
						<a class=\"perma\" style=\"font-size:8px; color:" . $topfanbox['fantag_right_textcolor'] . "\" href=\"" . htmlspecialchars( $fantag_title->getFullURL() ) . "\" title=\"{$topfanbox['fantag_title']}\">" . $this->msg( 'fanbox-perma' )->plain() . "</a>
						<table class=\"fanBoxTable\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
						<tr>
							<td id=\"fanBoxLeftSideOutput\" style=\"color:" . $topfanbox['fantag_left_textcolor'] . "; font-size:$leftfontsize\" bgcolor=\"" . $topfanbox['fantag_left_bgcolor'] . "\">" . $fantag_leftside . "</td>
							<td id=\"fanBoxRightSideOutput\" style=\"color:" . $topfanbox['fantag_right_textcolor'] . "; font-size:$rightfontsize\" bgcolor=\"" . $topfanbox['fantag_right_bgcolor'] . "\">" . $right_text . "</td>
						</tr>
						</table>
						</div>
					</div>
				</div>";

				if ( $user->isRegistered() ) {
					if ( $check_user_fanbox == 0 ) {
						$output .= '
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $topfanbox['fantag_id'] . '">
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
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $topfanbox['fantag_id'] . '">
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

				if ( $user->getID() == 0 ) {
					$output .= '<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $topfanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0" width="258px">
						<tr>
							<td align="center">' .
								$this->msg( 'fanbox-add-fanbox-login' )->parse() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-cancel-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" />
							</td>
						</tr>
					</table>
					</div>';
				}

				$output .= '</div></span>';
				$output .= '<div class="top-fanbox-users">
					<table>
						<tr>
							<td class="centerheight">
								<b><a href="' . htmlspecialchars( $fantag_title->getFullURL() ) . '">' .
									$this->msg(
										'fanbox-members'
									)->numParams(
										$topfanbox['fantag_count']
									)->parse() .
								'</a></b>
							</td>
						</tr>
					</table>
				</div>';
				$output .= '<div class="visualClear"></div>';
				$output .= '</div>';

				$x++;
			}

			$output .= '</div><div class="visualClear"></div>';
		}

		if ( $topfanboxCategory ) {
			$x = 1;

			$output .= '<div class="top-fanboxes">';

			// This variable wasn't originally defined, I'm not sure that this
			// is 100% correct, but...
			$categoryfanboxes = $this->getFanBoxByCategory( $topfanboxCategory );

			foreach ( $categoryfanboxes as $categoryfanbox ) {
				$check_user_fanbox = $this->checkIfUserHasFanbox( $categoryfanbox['fantag_id'] );

				if ( $categoryfanbox['fantag_image_name'] ) {
					$fantag_image_width = 45;
					$fantag_image_height = 53;
					$fantag_image = $repoGroup->findFile( $categoryfanbox['fantag_image_name'] );
					$fantag_image_url = '';
					if ( is_object( $fantag_image ) ) {
						$fantag_image_url = $fantag_image->createThumb(
							$fantag_image_width,
							$fantag_image_height
						);
					}
					$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '"/>';
				}

				if ( $categoryfanbox['fantag_left_text'] == '' ) {
					$fantag_leftside = $fantag_image_tag;
				} else {
					$fantag_leftside = $categoryfanbox['fantag_left_text'];
				}

				if ( $categoryfanbox['fantag_left_textsize'] == 'mediumfont' ) {
					$leftfontsize = '14px';
				}
				if ( $categoryfanbox['fantag_left_textsize'] == 'bigfont' ) {
					$leftfontsize = '20px';
				}

				if ( $categoryfanbox['fantag_right_textsize'] == 'smallfont' ) {
					$rightfontsize = '12px';
				}
				if ( $categoryfanbox['fantag_right_textsize'] == 'mediumfont' ) {
					$rightfontsize = '14px';
				}

				// Get permalink
				$fantag_title = Title::makeTitle( NS_FANTAG, $categoryfanbox['fantag_title'] );

				// Get creator
				$creator = User::newFromActorId( $categoryfanbox['fantag_actor'] );
				$userftusername = $creator->getName();
				$userftuserid = $creator->getId();
				$user_title = $creator->getUserPage();
				$avatar = new wAvatar( $userftuserid, 'm' );

				$output .= "
				<div class=\"top-fanbox-row\">
				<span class=\"top-fanbox-num\">{$x}.</span><div class=\"top-fanbox\">

				<div class=\"fanbox-item\">

				<div class=\"individual-fanbox\" id=\"individualFanbox" . $categoryfanbox['fantag_id'] . "\">
				<div class=\"show-message-container\" id=\"show-message-container" . $categoryfanbox['fantag_id'] . "\">
					<div class=\"permalink-container\">
					<a class=\"perma\" style=\"font-size:8px; color:" . $categoryfanbox['fantag_right_textcolor'] . "\" href=\"" . htmlspecialchars( $fantag_title->getFullURL() ) . "\" title=\"{$categoryfanbox['fantag_title']}\">" . $this->msg( 'fanbox-perma' )->plain() . "</a>
					<table class=\"fanBoxTable\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
						<tr>
							<td id=\"fanBoxLeftSideOutput\" style=\"color:" . $categoryfanbox['fantag_left_textcolor'] . "; font-size:$leftfontsize\" bgcolor=\"" . $categoryfanbox['fantag_left_bgcolor'] . "\">" . $fantag_leftside . "</td>
							<td id=\"fanBoxRightSideOutput\" style=\"color:" . $categoryfanbox['fantag_right_textcolor'] . "; font-size:$rightfontsize\" bgcolor=\"" . $categoryfanbox['fantag_right_bgcolor'] . "\">" . $categoryfanbox['fantag_right_text'] . '</td>
						</tr>
					</table>
					</div>
				</div>
				</div>';

				if ( $user->isRegistered() ) {
					if ( $check_user_fanbox == 0 ) {
						$output .= '
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $categoryfanbox['fantag_id'] . '">
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
					<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $categoryfanbox['fantag_id'] . '">
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

				if ( $user->getID() == 0 ) {
					$output .= '<div class="fanbox-pop-up-box" id="fanboxPopUpBox' . $categoryfanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0" width="258px">
						<tr>
							<td align="center">' .
								$this->msg( 'fanbox-add-fanbox-login' )->parse() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-cancel-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" />
							</td>
						</tr>
					</table>
					</div>';
				}

				$output .= '</div></div>';
				$output .= '<div class="top-fanbox-creator">
				<table>
					<tr>
					<td class="centerheight"> <b> ' . $this->msg( 'fanbox-created-by' )->parse() . ' <b> </td>
					<td class="centerheight"> <b> <a href="' . htmlspecialchars( $user_title->getFullURL() ) . "\">
						{$avatar->getAvatarURL()}
						</a></b>
					</td>
					</tr>
				</table>
				</div>";
				$output .= '<div class="top-fanbox-users">
					<table>
						<tr>
							<td class="centerheight">
								<b><a href="' . htmlspecialchars( $fantag_title->getFullURL() ) . '">' .
									$this->msg( 'fanbox-members' )->numParams( $categoryfanbox['fantag_count'] )->parse() .
								'</a></b>
							</td>
						</tr>
					</table>
				</div>';
				$output .= '<div class="visualClear"></div>';
				$output .= '</div>';

				$x++;

			}
			$output .= '</div><div class="visualClear"></div>';

		}

		$out->addHTML( $output );
	}

	function getTopFanboxes( $orderBy ) {
		$dbr = wfGetDB( DB_PRIMARY );

		$res = $dbr->select(
			'fantag',
			[
				'fantag_id', 'fantag_title', 'fantag_pg_id', 'fantag_left_text',
				'fantag_left_textcolor', 'fantag_left_bgcolor',
				'fantag_right_text', 'fantag_right_textcolor',
				'fantag_right_bgcolor', 'fantag_image_name',
				'fantag_left_textsize', 'fantag_right_textsize', 'fantag_count',
				'fantag_actor', 'fantag_date'
			],
			[],
			__METHOD__,
			[ 'ORDER BY' => "$orderBy DESC", 'LIMIT' => 50 ]
		);

		$topFanboxes = [];
		foreach ( $res as $row ) {
			$topFanboxes[] = [
				'fantag_id' => $row->fantag_id,
				'fantag_title' => $row->fantag_title,
				'fantag_pg_id' => $row->fantag_pg_id,
				'fantag_left_text' => $row->fantag_left_text,
				'fantag_left_textcolor' => $row->fantag_left_textcolor,
				'fantag_left_bgcolor' => $row->fantag_left_bgcolor,
				'fantag_right_text' => $row->fantag_right_text,
				'fantag_right_textcolor' => $row->fantag_right_textcolor,
				'fantag_right_bgcolor' => $row->fantag_right_bgcolor,
				'fantag_image_name' => $row->fantag_image_name,
				'fantag_left_textsize' => $row->fantag_left_textsize,
				'fantag_right_textsize' => $row->fantag_right_textsize,
				'fantag_count' => $row->fantag_count,
				'fantag_actor' => $row->fantag_actor,
				'fantag_date' => $row->fantag_date,
			];
		}

		return $topFanboxes;
	}

	function checkIfUserHasFanbox( $userft_fantag_id ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'user_fantag',
			[ 'COUNT(*) AS count' ],
			[
				'userft_actor' => $this->getUser()->getActorId(),
				'userft_fantag_id' => intval( $userft_fantag_id )
			],
			__METHOD__
		);
		$row = $dbr->fetchObject( $res );
		$check_fanbox_count = 0;
		if ( $row ) {
			$check_fanbox_count = $row->count;
		}
		return $check_fanbox_count;
	}

	public function getFanBoxByCategory( $category ) {
		$dbr = wfGetDB( DB_PRIMARY );

		$res = $dbr->select(
			[ 'fantag', 'categorylinks' ],
			[
				'fantag_id', 'fantag_title', 'fantag_pg_id',
				'fantag_left_text', 'fantag_left_textcolor',
				'fantag_left_bgcolor', 'fantag_right_text',
				'fantag_right_textcolor', 'fantag_right_bgcolor',
				'fantag_image_name', 'fantag_left_textsize',
				'fantag_right_textsize', 'fantag_count',
				'fantag_actor'
			],
			[ 'cl_to' => $category ],
			__METHOD__,
			[ 'ORDER BY' => 'fantag_count DESC' ],
			[ 'categorylinks' => [ 'INNER JOIN', 'cl_from = fantag_pg_id' ] ]
		);

		$categoryFanboxes = [];
		foreach ( $res as $row ) {
			$categoryFanboxes[] = [
				'fantag_id' => $row->fantag_id,
				'fantag_title' => $row->fantag_title,
				'fantag_pg_id' => $row->fantag_pg_id,
				'fantag_left_text' => $row->fantag_left_text,
				'fantag_left_textcolor' => $row->fantag_left_textcolor,
				'fantag_left_bgcolor' => $row->fantag_left_bgcolor,
				'fantag_right_text' => $row->fantag_right_text,
				'fantag_right_textcolor' => $row->fantag_right_textcolor,
				'fantag_right_bgcolor' => $row->fantag_right_bgcolor,
				'fantag_image_name' => $row->fantag_image_name,
				'fantag_left_textsize' => $row->fantag_left_textsize,
				'fantag_right_textsize' => $row->fantag_right_textsize,
				'fantag_count' => $row->fantag_count,
				'fantag_actor' => $row->fantag_actor
			];
		}

		return $categoryFanboxes;
	}
}
