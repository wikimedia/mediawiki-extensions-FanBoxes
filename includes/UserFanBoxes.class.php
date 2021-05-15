<?php
/**
 * Functions for handling the displaying of an individual user's fanboxes.
 *
 * Used by SpecialFanBoxes.php and UserBoxesHook.php in FanBoxes, and also by
 * SocialProfile's UserProfilePage.
 *
 * @file
 */
class UserFanBoxes {

	/**
	 * @var User User object whose userboxes we're dealing with here
	 */
	public $user;

	/**
	 * Constructor
	 *
	 * @param User|string $user User object (preferred) or user name (legacy b/c)
	 */
	public function __construct( $user ) {
		if ( $user instanceof User ) {
			$this->user = $user;
		} else {
			$this->user = User::newFromName( $user );
		}
	}

	/**
	 * Used on SpecialViewFanBoxes page to get all the user's fanboxes
	 *
	 * @param int $limit LIMIT for the SQL query
	 * @param int $page The current page; used to build pagination links
	 *                       and also used here to calculate the OFFSET for the
	 *                       SQL query
	 * @return array
	 */
	public function getUserFanboxes( $limit = 0, $page = 0 ) {
		$dbr = wfGetDB( DB_REPLICA );

		$params['ORDER BY'] = 'userft_date DESC';
		if ( $limit > 0 ) {
			$limitvalue = 0;
			if ( $page ) {
				$limitvalue = $page * $limit - ( $limit );
			}
			$params['LIMIT'] = $limit;
			$params['OFFSET'] = $limitvalue;
		}

		$res = $dbr->select(
			[ 'fantag', 'user_fantag' ],
			[
				'fantag_id',
				'fantag_title',
				'fantag_left_text',
				'fantag_left_textcolor',
				'fantag_left_bgcolor',
				'fantag_right_text',
				'fantag_right_textcolor',
				'fantag_right_bgcolor',
				'userft_date',
				'fantag_image_name',
				'fantag_left_textsize',
				'fantag_right_textsize'
			],
			[ 'userft_actor' => $this->user->getActorId() ],
			__METHOD__,
			$params,
			[ 'user_fantag' => [ 'INNER JOIN', 'userft_fantag_id = fantag_id' ] ]
		);

		$userFanboxes = [];
		foreach ( $res as $row ) {
			$userFanboxes[] = [
				'fantag_id' => $row->fantag_id,
				'fantag_title' => $row->fantag_title,
				'fantag_left_text' => $row->fantag_left_text,
				'fantag_left_textcolor' => $row->fantag_left_textcolor,
				'fantag_left_bgcolor' => $row->fantag_left_bgcolor,
				'fantag_right_text' => $row->fantag_right_text,
				'fantag_right_textcolor' => $row->fantag_right_textcolor,
				'fantag_right_bgcolor' => $row->fantag_right_bgcolor,
				'fantag_image_name' => $row->fantag_image_name,
				'fantag_left_textsize' => $row->fantag_left_textsize,
				'fantag_right_textsize' => $row->fantag_right_textsize
			];
		}

		return $userFanboxes;
	}

	/**
	 * Used on Special:ViewFanBoxes page to get the count of a user's fanboxes
	 * so we can build the prev/next bar
	 *
	 * @return int Amount of fanboxes the user has, or 0 if they have none
	 */
	public function getFanBoxCount() {
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->select(
			'user_fantag',
			[ 'COUNT(*) AS count' ],
			[ 'userft_actor' => $this->user->getActorId() ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);
		$row = $dbw->fetchObject( $res );
		$user_fanbox_count = 0;
		if ( $row ) {
			$user_fanbox_count = $row->count;
		}
		return $user_fanbox_count;
	}

	/**
	 * Used on Special:ViewFanBoxes to know whether popup box should be Add or
	 * Remove fanbox
	 *
	 * @param int $userft_fantag_id Userbox ID number
	 * @return int
	 */
	public function checkIfUserHasFanbox( $userft_fantag_id ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->select(
			'user_fantag',
			[ 'COUNT(*) AS count' ],
			[
				'userft_actor' => $this->user->getActorId(),
				'userft_fantag_id' => intval( $userft_fantag_id )
			],
			__METHOD__
		);
		$row = $dbw->fetchObject( $res );
		$check_fanbox_count = 0;
		if ( $row ) {
			$check_fanbox_count = $row->count;
		}
		return $check_fanbox_count;
	}

}
