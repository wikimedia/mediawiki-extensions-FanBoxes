<?php
/**
 * <userboxes> parser hook extension -- allows displaying your own userboxes in
 * a wiki page
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class UserBoxesHook {
	/**
	 * Register the <userboxes> tag with the Parser.
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'userboxes', [ 'UserBoxesHook', 'renderUserBoxesHook' ] );
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
		global $wgOut;

		$user = $parser->getUserIdentity();
		$pOut = $parser->getOutput();
		$pOut->updateCacheExpiry( 0 );

		// Add CSS & JS
		$pOut->addModuleStyles( [ 'ext.fanBoxes.styles' ] );
		$pOut->addModules( [ 'ext.fanBoxes.scripts' ] );

		$user_name = $args['user'] ?? $user->getName();

		$limit = 10;
		if ( isset( $args['limit'] ) && is_numeric( $args['limit'] ) ) {
			$limit = intval( $args['limit'] );
		}

		$f = new UserFanBoxes( $user_name );

		$fanboxes = $f->getUserFanboxes( $limit );

		$output = '';

		if ( $fanboxes ) {
			$output .= '<div class="clearfix"><div class="user-fanbox-container">';

			$x = 1;
			$services = MediaWikiServices::getInstance();
			$repoGroup = $services->getRepoGroup();
			$tagParser = $services->getParserFactory()->create();
			$linkRenderer = $services->getLinkRenderer();

			foreach ( $fanboxes as $fanbox ) {
				$check_user_fanbox = $f->checkIfUserHasFanbox( $fanbox['fantag_id'] );

				$fantag_image_tag = '';
				if ( $fanbox['fantag_image_name'] ) {
					$fantag_image_width = 45;
					$fantag_image_height = 53;
					$fantag_image = $repoGroup->findFile( $fanbox['fantag_image_name'] );
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

				$permaLink = $linkRenderer->makeLink(
					$fantag_title,
					wfMessage( 'fanbox-perma' )->plain(),
					[
						'class' => 'perma',
						'title' => $fanbox['fantag_title'],
						'style' => 'font-size:8px; color:' . $fanbox['fantag_right_textcolor']
					]
				);

				// Output fanboxes
				$output .= '<span class="top-fanbox"><div class="fanbox-item">
				<div class="individual-fanbox" id="individualFanbox' . (int)$fanbox['fantag_id'] . '">
				<div class="show-message-container-profile" id="show-message-container' . (int)$fanbox['fantag_id'] . '">
					<div class="relativeposition">' . $permaLink .
					'<table class="fanBoxTableProfile" border="0" cellpadding="0" cellspacing="0">
						<tr>
							<td id="fanBoxLeftSideOutputProfile" style="color:' . htmlspecialchars( $fanbox['fantag_left_textcolor'], ENT_QUOTES ) . "; font-size:$leftFontSize; background-color:" . htmlspecialchars( $fanbox['fantag_left_bgcolor'], ENT_QUOTES ) . ';">' . $fantag_leftside . '</td>
							<td id="fanBoxRightSideOutputProfile" style="color:' . htmlspecialchars( $fanbox['fantag_right_textcolor'], ENT_QUOTES ) . "; font-size:$rightFontSize; background-color:" . htmlspecialchars( $fanbox['fantag_right_bgcolor'], ENT_QUOTES ) . ';">' . $right_text . '</td>
						</tr>
					</table>
					</div>
				</div>
				</div>';

				if ( $user->isRegistered() ) {
					if ( $check_user_fanbox == 0 ) {
						$output .= '
					<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . (int)$fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size:10px" align="center">' .
								wfMessage( 'fanbox-add-fanbox' )->escaped() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-add-button-half" value="' . wfMessage( 'fanbox-add' )->escaped() . '" size="10" />
								<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="10" />
							</td>
						</tr>
					</table>
					</div>';
					} else {
						$output .= '
					<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . (int)$fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size:10px" align="center">' .
								wfMessage( 'fanbox-remove-fanbox' )->escaped() .
							'</td>
						</tr>
						<tr>
							<td align="center">
								<input type="button" class="fanbox-remove-button-half" value="' . wfMessage( 'fanbox-remove' )->escaped() . '" size="10" />
								<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="10" />
							</td>
						</tr>
					</table>
					</div>';
					}
				}

				if ( $user->getId() == 0 ) {
					$output .= '<div class="fanbox-pop-up-box-profile" id="fanboxPopUpBox' . (int)$fanbox['fantag_id'] . '">
					<table cellpadding="0" cellspacing="0">
						<tr>
							<td style="font-size: 10px" align="center">' .
								wfMessage( 'fanbox-add-fanbox-login' )->parse() .
							'</td>
							<tr>
								<td align="center">
									<input type="button" class="fanbox-cancel-button" value="' . wfMessage( 'cancel' )->escaped() . '" size="10" />
								</td>
							</tr>
						</table>
					</div>';
				}

				$output .= '</div></span><div class="visualClear"></div>';
				$x++;
			}

			$output .= '</div></div>';
		}

		return $output;
	}

}
