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
		$context = $this->getContext();
		$out = $context->getOutput();
		$user = $context->getUser();

		// Add JS
		$out->addModules( 'ext.fanBoxes' );

		// Set the page title
		$out->setHTMLTitle( $this->getTitle()->getText() );
		$out->setPageTitle( $this->getTitle()->getText() );

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

		if ( $user->isLoggedIn() ) {
			$check = $this->fan->checkIfUserHasFanBox( $user );
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

		$out->addHTML( $output );

		global $wgFanBoxPageDisplay;
		// Display comments, if we want to display those.
		if ( $wgFanBoxPageDisplay['comments'] ) {
			$out->addWikiTextAsInterface( '<comments/>' );
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
			[ 'DISTINCT userft_actor' ],
			[ 'fantag_pg_id' => $pageTitleId ],
			__METHOD__,
			[],
			[ 'fantag' => [ 'INNER JOIN', 'userft_fantag_id = fantag_id' ] ]
		);

		$fanboxHolders = [];

		foreach ( $res as $row ) {
			$fanboxHolders[] = [
				'userft_actor' => $row->userft_actor
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
			$actor = User::newFromActorId( $fanboxHolder['userft_actor'] );
			if ( !$actor || !$actor instanceof User ) {
				continue;
			}
			$avatar = new wAvatar( $actor->getId(), 'ml' );
			$output .= "<a href=\"" . htmlspecialchars( $actor->getUserPage()->getFullURL() ) . "\">
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
		return '<form name="embed_fan" action="">' . wfMessage( 'fanbox-embed' )->plain() .
			" <input name='embed_code' type='text' value='{$code}' onclick='javascript:document.embed_fan.embed_code.focus();document.embed_fan.embed_code.select();' readonly='readonly' /></form>";
	}

}
