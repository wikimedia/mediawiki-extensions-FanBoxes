<?php
/**
 * This class handles the views of UserBox: pages.
 *
 * @file
 * @ingroup Extensions
 */
class FanBoxPage extends Article {

	public $title = null;
	public $authors = [];

	/**
	 * @var FanBox: FanBox for the current Title
	 */
	public $fan;

	function __construct( Title $title ) {
		parent::__construct( $title );
	}

	function view() {
		global $wgOut, $wgUser;

		// Add JS
		$wgOut->addModules( 'ext.fanBoxes' );

		// Set the page title
		$wgOut->setHTMLTitle( $this->getTitle()->getText() );
		$wgOut->setPageTitle( $this->getTitle()->getText() );

		// Don't throw a bunch of E_NOTICEs when we're viewing the page of a
		// nonexistent fanbox
		if ( !$this->getID() ) {
			parent::view();
			return '';
		}

		$this->fan = new FanBox( $this->getTitle() );
		$fanboxTitle = Title::makeTitle( NS_FANTAG, $this->fan->getName() );

		$output = '';

		$output .= '<div class="fanbox-page-container clearfix">' .
			$this->fan->outputFanBox();
		$fantag_id = $this->fan->getFanBoxId();

		$output .= '<div id="show-message-container' . $fantag_id . '">';

		if ( $wgUser->isLoggedIn() ) {
			$check = $this->fan->checkIfUserHasFanBox();
			if ( $check == 0 ) {
				$output .= $this->fan->outputIfUserDoesntHaveFanBox();
			} else {
				$output .= $this->fan->outputIfUserHasFanBox();
			}
		} else {
			$output .= $this->fan->outputIfUserNotLoggedIn();
		}

		$output .= '</div>
			<div class="user-embed-tag">' .
				$this->getEmbedThisTag() .
			'</div>
			<div class="users-with-fanbox">
				<h2>' . wfMessage( 'fanbox-users-with-fanbox' )->plain() . '</h2>
				<div class="users-with-fanbox-message">' .
					wfMessage( 'fanbox-users-with-fanbox-message' )->plain() .
				'</div>' .
				$this->fanBoxHolders() . "\n" .
			'</div>
		</div>';

		$wgOut->addHTML( $output );

		global $wgFanBoxPageDisplay;
		// Display comments, if we want to display those.
		if ( $wgFanBoxPageDisplay['comments'] ) {
			$wgOut->addWikiText( '<comments/>' );
		}

		parent::view();
	}

	/**
	 * Get the users who have the current fanbox.
	 *
	 * @return Array array containing the users' names and IDs or an empty
	 *                array
	 */
	function getFanBoxHolders() {
		$pageTitleId = $this->getTitle()->getArticleID();

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'user_fantag', 'fantag' ],
			[ 'DISTINCT userft_user_name', 'userft_user_id' ],
			[ 'fantag_pg_id' => $pageTitleId ],
			__METHOD__,
			[],
			[ 'fantag' => [ 'INNER JOIN', 'userft_fantag_id = fantag_id' ] ]
		);

		$fanboxHolders = [];

		foreach ( $res as $row ) {
			$fanboxHolders[] = [
				'userft_user_name' => $row->userft_user_name,
				'userft_user_id' => $row->userft_user_id
			];
		}

		return $fanboxHolders;
	}

	/**
	 * Get the users who have the current fanbox from the database and output
	 * their avatars.
	 *
	 * @return String HTML
	 */
	function fanBoxHolders() {
		$output = '';
		$fanboxHolders = $this->getFanBoxHolders();

		foreach ( $fanboxHolders as $fanboxHolder ) {
			$userftusername = $fanboxHolder['userft_user_name'];
			$userftuserid = $fanboxHolder['userft_user_id'];
			$userTitle = Title::makeTitle( NS_USER, $fanboxHolder['userft_user_name'] );
			$avatar = new wAvatar( $fanboxHolder['userft_user_id'], 'ml' );
			$output .= "<a href=\"" . htmlspecialchars( $userTitle->getFullURL() ) . "\">
				{$avatar->getAvatarURL()}
			</a>";
		}

		return $output;
	}

	/**
	 * Get the wikitext code for embedding this fanbox on a wiki page.
	 *
	 * @return String HTML
	 */
	public function getEmbedThisTag() {
		$code = $this->fan->getEmbedThisCode();
		$code = preg_replace( '/[\n\r\t]/', '', $code ); // remove any non-space whitespace
		$code = str_replace( '_', ' ', $code ); // replace underscores with spaces
		return '<form name="embed_fan" action="">' . wfMessage( 'fan-embed' )->plain() .
			" <input name='embed_code' type='text' value='{$code}' onclick='javascript:document.embed_fan.embed_code.focus();document.embed_fan.embed_code.select();' readonly='readonly' /></form>";
	}

}