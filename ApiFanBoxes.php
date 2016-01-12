<?php
/**
 * FanBoxes API module
 *
 * @file
 * @ingroup API
 * @date 29 August 2013
 * @see http://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiFanBoxes extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		wfSuppressWarnings();
		$what = $params['what'];
		$pageName = $params['page_name'];
		$addRemove = $params['addRemove'];
		$title = $params['title'];
		$individualFantagId = $params['fantagId'];
		$id = $params['id'];
		$style = $params['style'];
		wfRestoreWarnings();

		// Ensure that we know what to do...
		if ( !$what || $what === null ) {
			$this->dieUsageMsg( 'missingparam' );
		}

		// Ensure that we have all the parameters required by each respective
		// sub-function, too
		if ( $what == 'checkTitleExistence' && ( !$pageName || $pageName === null ) ) {
			$this->dieUsage( 'missingparam', 'asdf' );
		} elseif (
			$what == 'showAddRemoveMessage' &&
			(
				!$addRemove || $addRemove === null || !$title || $title === null ||
				!$individualFantagId || $individualFantagId === null
			)
		)
		{
			$this->dieUsage( 'missingparam', 'qwerty' );
		} elseif (
			$what == 'messageAddRemoveUserPage' &&
			(
				!$addRemove || $addRemove === null || !$individualFantagId || $individualFantagId === null ||
				!$style || $style === null
			)
		)
		{
			$this->dieUsage( 'missingparam', 'fsdf' );
		}

		switch ( $what ) {
			case 'checkTitleExistence':
				$output = $this->checkTitleExistence( $pageName );
				break;
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
			array( 'result' => $output )
		);

		return true;
	}

	function showAddRemoveMessage( $addRemove, $title, $individual_fantag_id ) {
		$out = '';

		$fanbox = FanBox::newFromName( $title );

		if ( $addRemove === 1 ) {
			$fanbox->changeCount( $individual_fantag_id, +1 );
			$fanbox->addUserFan( $individual_fantag_id );

			if ( $this->getUser()->isLoggedIn() ) {
				$check = $fanbox->checkIfUserHasFanBox();
				if ( $check === 0 ) {
					$out .= $fanbox->outputIfUserDoesntHaveFanBox();
				} else {
					$out .= $fanbox->outputIfUserHasFanBox();
				}
			} else {
				$out .= $fanbox->outputIfUserNotLoggedIn();
			}

			$out.= '<div class="show-individual-addremove-message">' .
				wfMessage( 'fanbox-successful-add' )->plain() .
			'</div>';
		}

		if ( $addRemove === 2 ) {
			$fanbox->changeCount( $individual_fantag_id, -1 );
			$fanbox->removeUserFanBox( $individual_fantag_id );

			if ( $this->getUser()->isLoggedIn() ) {
				$check = $fanbox->checkIfUserHasFanBox();
				if ( $check === 0 ) {
					$out .= $fanbox->outputIfUserDoesntHaveFanBox();
				} else {
					$out .= $fanbox->outputIfUserHasFanBox();
				}
			} else {
				$out .= $fanbox->outputIfUserNotLoggedIn();
			}

			$out.= '<div class="show-individual-addremove-message">' .
				wfMessage( 'fanbox-successful-remove' )->plain() .
			'</div>';
		}

		return $out;
	}

	function messageAddRemoveUserPage( $addRemove, $id, $style ) {
		$out = '';
		$number = 0;

		$dbw = wfGetDB( DB_MASTER );

		if ( $addRemove == 1 ) {
			$number = +1;

			$dbw->insert(
				'user_fantag',
				array(
					'userft_fantag_id' => $id,
					'userft_user_id' => $this->getUser()->getId(),
					'userft_user_name' => $this->getUser()->getName(),
					'userft_date' => date( 'Y-m-d H:i:s' ),
				),
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
				array(
					'userft_user_id' => $this->getUser()->getId(),
					'userft_fantag_id' => $id
				),
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
				/* SET */array( "fantag_count = fantag_count+{$number}" ),
				/* WHERE */array( 'fantag_id' => $id ),
				__METHOD__
			);

		}

		return $out;
	}

	/**
	 * Check if there's already a FanBox with the given name.
	 *
	 * @param $page_name String: FanBox name to check
	 * @return String: "OK" if the page can be created, else "Page exists"
	 */
	function checkTitleExistence( $page_name ) {
		// Construct page title object to convert to Database Key
		$pageTitle = Title::makeTitle( NS_MAIN, urldecode( $page_name ) );
		$dbKey = $pageTitle->getDBkey();

		// Database key would be in page title if the page already exists
		$dbw = wfGetDB( DB_MASTER );
		$s = $dbw->selectRow(
			'page',
			array( 'page_id' ),
			array( 'page_title' => $dbKey, 'page_namespace' => NS_FANTAG ),
			__METHOD__
		);

		if ( $s !== false ) {
			return 'Page exists';
		} else {
			return 'OK';
		}
	}

	/**
	 * @return String: human-readable module description
	 */
	public function getDescription() {
		return 'Backend API for the FanBoxes extension';
	}

	/**
	 * @return Array
	 */
	public function getAllowedParams() {
		return array(
			'what' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'addRemove' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'fantagId' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'page_name' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'style' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'what' => 'What to do?',
			'addRemove' => '1 to add a userbox to a user profile, 2 to delete it',
			'style' => 'Class for the success div element',
			'fantagId' => 'UserBox identifier (number)',
			'page_name' => 'Name of the page (in the userbox namespace) whose existence you want to check',
			'title' => 'TODO DOCUMENT ME!',
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'api.php?action=fanboxes&what=checkTitleExistence&page_name=Foo bar' => 'Check if there\'s a fanbox called "Foo bar" in the database'
		);
	}
}