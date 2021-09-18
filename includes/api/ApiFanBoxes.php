<?php
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

		Wikimedia\suppressWarnings();
		$what = $params['what'];
		$pageName = $params['page_name'];
		$addRemove = $params['addRemove'];
		$title = $params['title'];
		$individualFantagId = $params['fantagId'];
		$id = $params['id'];
		$style = $params['style'];
		Wikimedia\restoreWarnings();

		// Ensure that we know what to do...
		if ( !$what || $what === null ) {
			$this->dieWithError( [ 'apierror-missingparam', 'what' ], 'missingparam' );
		}

		// Ensure that we have all the parameters required by each respective
		// sub-function, too
		if (
			$what == 'showAddRemoveMessage' &&
			(
				!$addRemove || $addRemove === null || !$title || $title === null ||
				!$individualFantagId || $individualFantagId === null
			)
		) {
			$this->requireAtLeastOneParameter( $params, 'addRemove', 'title', 'fantagId' );
		} elseif (
			$what == 'messageAddRemoveUserPage' &&
			(
				!$addRemove || $addRemove === null || !$individualFantagId || $individualFantagId === null ||
				!$style || $style === null
			)
		) {
			$this->requireAtLeastOneParameter( $params, 'addRemove', 'fantagId', 'style' );
		}

		switch ( $what ) {
			case 'messageAddRemoveUserPage':
				$output = $this->messageAddRemoveUserPage( $addRemove, $individualFantagId, $style );
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

	function showAddRemoveMessage( $addRemove, $title, $individual_fantag_id ) {
		$out = '';

		$fanbox = FanBox::newFromName( $title );
		$user = $this->getUser();

		if ( $addRemove === 1 ) {
			$fanbox->changeCount( $individual_fantag_id, +1 );
			$fanbox->addUserFan( $user, $individual_fantag_id );

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
				wfMessage( 'fanbox-successful-add' )->plain() .
			'</div>';
		}

		if ( $addRemove === 2 ) {
			$fanbox->changeCount( $individual_fantag_id, -1 );
			$fanbox->removeUserFanBox( $user, $individual_fantag_id );

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
				wfMessage( 'fanbox-successful-remove' )->plain() .
			'</div>';
		}

		return $out;
	}

	function messageAddRemoveUserPage( $addRemove, $id, $style ) {
		$out = '';
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

			$out .= "<div class=\"$style\">" .
				wfMessage( 'fanbox-successful-add' )->plain() .
				'</div>';
		}

		if ( $addRemove == 2 ) {
			$number = -1;

			$dbw->delete(
				'user_fantag',
				[
					'userft_actor' => $this->getUser()->getActorId(),
					'userft_fantag_id' => $id
				],
				__METHOD__
			);

			$out .= "<div class=\"$style\">" .
				wfMessage( 'fanbox-successful-remove' )->plain() .
				'</div>';
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

		return $out;
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
			],
			'fantagId' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'style' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			// @todo Add some real examples + relevant i18n strings
			// 'action=fanboxes&what=messageAddRemoveUserPage&addRemove=2&id=66&style=border:1px red dotted;'
			//	=> 'apihelp-fanboxes-example-2',
		];
	}
}
