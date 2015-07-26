<?php
/**
 * <userboxes> parser hook extension -- allows displaying your own userboxes in
 * a wiki page
 *
 * @file
 * @ingroup Extensions
 */

class UserBoxesHook {
	/**
	 * Register the <userboxes> tag with the Parser.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'userboxes', array( 'UserBoxesHook', 'renderUserBoxesHook' ) );
		return true;
	}

	/**
	 * Callback for the above function.
	 *
	 * @param string $input User-supplied input
	 * @param array $args Tag arguments, if any
	 * @param Parser $parser
	 * @return string HTML
	 */
	public static function renderUserBoxesHook( $input, $args, $parser ) {
		global $wgOut, $wgUser, $wgMemc;

		$parser->disableCache();

		// Add CSS & JS
		$wgOut->addModules( 'ext.fanBoxes' );

		$user_name = ( isset( $args['user'] ) ? $args['user'] : $wgUser->getName() );

		$limit = 10;
		if ( isset( $args['limit'] ) && is_numeric( $args['limit'] ) ) {
			$limit = intval( $args['limit'] );
		}

		$f = new UserFanBoxes( $user_name );

		// Try cache
		//$key = wfMemcKey( 'user', 'profile', 'fanboxes', $f->user_id );
		//$data = $wgMemc->get( $key );

		//if ( !$data ) {
		//	wfDebug( "Got profile fanboxes for user {$user_name} from DB\n" );
		//	$fanboxes = $f->getUserFanboxes( 0, $limit );
		//	$wgMemc->set( $key, $fanboxes );
		//} else {
		//	wfDebug( "Got profile fanboxes for user {$user_name} from cache\n" );
		//	$fanboxes = $data;
		//}

		$fanboxes = $f->getUserFanboxes( 0, $limit );

		$fanbox_count = $f->getFanBoxCountByUsername( $user_name );
		$fanbox_link = SpecialPage::getTitleFor( 'ViewUserBoxes' );
		$per_row = 1;
		$output = '';

		if ( $fanboxes ) {
			$output .= '<div class="clearfix"><div class="user-fanbox-container">';

			$x = 1;
			$tagParser = new Parser();

			foreach ( $fanboxes as $fanbox ) {
				$check_user_fanbox = $f->checkIfUserHasFanbox( $fanbox['fantag_id'] );

				if ( $fanbox['fantag_image_name'] ) {
					$fantag_image_width = 45;
					$fantag_image_height = 53;
					$fantag_image = wfFindFile( $fanbox['fantag_image_name'] );
					$fantag_image_url = '';
					if ( is_object( $fantag_image ) ) {
						$fantag_image_url = $fantag_image->createThumb(
							$fantag_image_width,
							$fantag_image_height
						);
					}
					$fantag_image_tag = '<img alt="" src="' . $fantag_image_url . '" />';
				}

				if ( $fanbox['fantag_left_text'] == '' ) {
					$fantag_leftside = $fantag_image_tag;
				} else {
					$fantag_leftside = $fanbox['fantag_left_text'];
					$fantag_leftside = $tagParser->parse(
						$fantag_leftside,
						$parser->getTitle(),
						$wgOut->parserOptions(),
						false
					);
					$fantag_leftside = $fantag_leftside->getText();
				}

				$leftFontSize = '10px';
				if ( $fanbox['fantag_left_textsize'] == 'mediumfont' ) {
					$leftFontSize = '11px';
				}
				if ( $fanbox['fantag_left_textsize'] == 'bigfont' ) {
					$leftFontSize = '15px';
				}
				$rightFontSize = '10px';
				if ( $fanbox['fantag_right_textsize'] == 'smallfont' ) {
					$rightFontSize = '10px';
				}
				if ( $fanbox['fantag_right_textsize'] == 'mediumfont' ) {
					$rightFontSize = '11px';
				}

				// Get permalink
				$fantag_title = Title::makeTitle( NS_FANTAG, $fanbox['fantag_title'] );

				$right_text = $fanbox['fantag_right_text'];
				$right_text = $tagParser->parse(
					$right_text,
					$parser->getTitle(),
					$wgOut->parserOptions(),
					false
				);
				$right_text = $right_text->getText();

				$permaLink = Linker::link(
					$fantag_title,
					wfMessage( 'fanbox-perma' )->plain(),
					array(
						'class' => 'perma',
						'title' => $fanbox['fantag_title'],
						'style' => 'font-size:8px; color:' . $fanbox['fantag_right_textcolor']
					)
				);

				// Output fanboxes
				$output .= '<span class="top-fanbox"><div class="fanbox-item">
				<div class="individual-fanbox" id="individualFanbox' . $fanbox['fantag_id'] . '">
				<div class="show-message-container-profile" id="show-message-container' . $fanbox['fantag_id'] . '">
					<div class="relativeposition">' . $permaLink .
					'<table class="fanBoxTableProfile" border="0" cellpadding="0" cellspacing="0">
						<tr>
							<td id="fanBoxLeftSideOutputProfile" style="color:' . $fanbox['fantag_left_textcolor'] . "; font-size:$leftFontSize; background-color:" . $fanbox['fantag_left_bgcolor'] . ';">' . $fantag_leftside . '</td>
							<td id="fanBoxRightSideOutputProfile" style="color:' . $fanbox['fantag_right_textcolor'] . "; font-size:$rightFontSize; background-color:" . $fanbox['fantag_right_bgcolor'] . ';">' . $right_text . '</td>
						</tr>
					</table>
					</div>
				</div>
				</div>';

				if ( $wgUser->isLoggedIn() ) {
					if ( $check_user_fanbox == 0 ) {
						$output .= '
					<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . $fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size:10px" align="center">' .
								wfMessage( 'fanbox-add-fanbox' )->plain() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-add-button-half" value="' . wfMessage( 'fanbox-add' )->plain() . '" size="10" />
								<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="10" />
							</td>
						</tr>
					</table>
					</div>';
					} else {
						$output .= '
					<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . $fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size:10px" align="center">' .
								wfMessage( 'fanbox-remove-fanbox' )->plain() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-remove-button-half" value="' . wfMessage( 'fanbox-remove' )->plain() . '" size="10" />
								<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="10" />
							</td>
						</tr>
					</table>
					</div>';
					}
				}

				if ( $wgUser->getId() == 0 ) {
					$output .= '<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . $fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size: 10px" align="center">' .
								wfMessage( 'fanbox-add-fanbox-login' )->parse() .
							'</td>
							<tr>
								<td align="center">
									<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->plain() . '" size="10" />
								</td>
							</tr>
						</table>
					</div>';
				}

				$output .= '</div></span><div class="cleared"></div>';
				//if ( $x == count( $fanboxes ) || $x != 1 && $x % $per_row == 0 ) $output .= '<div class="cleared"></div>';
				$x++;
			}

			$output .= '</div></div>';
		}

		return $output;
	}

}