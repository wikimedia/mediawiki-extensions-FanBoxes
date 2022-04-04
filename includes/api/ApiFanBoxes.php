<?php

use Wikimedia\AtEase\AtEase;

/**
 * FanBoxes API module
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 * @phan-file-suppress PhanImpossibleTypeComparison Phan doesn't like the "is thing null?" checks here
 */
class ApiFanBoxes extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		AtEase::suppressWarnings();
		$what = $params['what'];
		$addRemove = $params['addRemove'];
		$title = $params['title'];
		$individualFantagId = $params['fantagId'];
		AtEase::restoreWarnings();

		// Ensure that we have all the parameters required by each respective
		// sub-function, too
		if ( $what == 'showAddRemoveMessage' && ( !$title || $title === null ) ) {
			$this->requireAtLeastOneParameter( $params, 'addRemove', 'title', 'fantagId' );
		}

		switch ( $what ) {
			case 'messageAddRemoveUserPage':
				$output = $this->messageAddRemoveUserPage( $addRemove, $individualFantagId );
				break;
			case 'showAddRemoveMessage':
				$output = $this->showAddRemoveMessage( $addRemove, $title, $individualFantagId );
				break;
			default:
				$output = '';
				break;
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $output ]
		);

		return true;
	}

	/**
	 * @param int $addRemove 1 for adding a fanbox, 2 for removing an existing one
	 * @param string $title FanBox name without the namespace
	 * @param int $individual_fantag_id FanBox internal ID number
	 * @return string HTML to be output
	 */
	function showAddRemoveMessage( $addRemove, $title, $individual_fantag_id ) {
		$out = $msgKey = '';

		$fanbox = FanBox::newFromName( $title );
		$user = $this->getUser();

		if ( $addRemove === 1 ) {
			$fanbox->changeCount( $individual_fantag_id, +1 );
			$fanbox->addUserFan( $user, $individual_fantag_id );

			$msgKey = 'fanbox-successful-add';
		} elseif ( $addRemove === 2 ) {
			$fanbox->changeCount( $individual_fantag_id, -1 );
			$fanbox->removeUserFanBox( $user, $individual_fantag_id );

			$msgKey = 'fanbox-successful-remove';
		}

		if ( $user->isRegistered() ) {
			$check = $fanbox->checkIfUserHasFanBox( $user );
			if ( $check === 0 ) {
				$out .= $fanbox->outputIfUserDoesntHaveFanBox();
			} else {
				$out .= $fanbox->outputIfUserHasFanBox();
			}
		} else {
			$out .= $fanbox->outputIfUserNotLoggedIn();
		}

		$out .= '<div class="show-individual-addremove-message">' .
			wfMessage( $msgKey )->escaped() .
		'</div>';

		return $out;
	}

	/**
	 * Handle adding and removing FanBoxes.
	 *
	 * @param int $addRemove 1 for adding a fanbox, 2 for removing an existing one
	 * @param int $id FanBox internal ID number
	 * @return string i18n message key name for the action that was done
	 */
	function messageAddRemoveUserPage( $addRemove, $id ) {
		$msgKey = '';
		$number = 0;

		$dbw = wfGetDB( DB_PRIMARY );

		if ( $addRemove == 1 ) {
			$number = +1;

			$dbw->insert(
				'user_fantag',
				[
					'userft_fantag_id' => $id,
					'userft_actor' => $this->getUser()->getActorId(),
					'userft_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) ),
				],
				__METHOD__
			);

			$msgKey = 'fanbox-successful-add';
		} elseif ( $addRemove == 2 ) {
			$number = -1;

			$dbw->delete(
				'user_fantag',
				[
					'userft_actor' => $this->getUser()->getActorId(),
					'userft_fantag_id' => $id
				],
				__METHOD__
			);

			$msgKey = 'fanbox-successful-remove';
		}

		// Checking for $number !== 0 obviously doesn't work...
		if ( $number == -1 || $number == +1 ) {
			$dbw->update(
				'fantag',
				/* SET */[ "fantag_count = fantag_count+{$number}" ],
				/* WHERE */[ 'fantag_id' => $id ],
				__METHOD__
			);
		}

		return $msgKey;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'what' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'addRemove' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'fantagId' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			// @todo Add some real examples + relevant i18n strings
			// 'action=fanboxes&what=messageAddRemoveUserPage&addRemove=2&fantagId=66'
			//	=> 'apihelp-fanboxes-example-2',
		];
	}
}
