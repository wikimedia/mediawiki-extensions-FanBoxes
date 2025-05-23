<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * This class handles the views of UserBox: pages.
 *
 * @file
 * @ingroup Extensions
 */
class FanBoxPage extends Article {

	/**
	 * @var FanBox FanBox for the current Title
	 */
	public $fan;

	/**
	 * @param MediaWiki\Title\Title $title
	 */
	function __construct( Title $title ) {
		parent::__construct( $title );
	}

	function view() {
		$context = $this->getContext();
		$out = $context->getOutput();
		$request = $context->getRequest();
		$user = $context->getUser();

		// Set the page title
		$out->setHTMLTitle( $this->getTitle()->getText() );
		$out->setPageTitle( $this->getTitle()->getText() );

		// Don't throw a bunch of E_NOTICEs when we're viewing the page of a
		// nonexistent fanbox
		if ( !$this->getPage()->getId() ) {
			parent::view();
			return;
		}

		// Add JS
		$out->addModules( 'ext.fanBoxes.scripts' );

		// Add CSS
		$out->addModuleStyles( [
			'ext.fanBoxes.styles',
			// Add CSS specific to UserBox: pages
			'ext.fanBoxes.fanboxpage'
		] );

		$services = MediaWikiServices::getInstance();

		// For diff views, show the diff *above* (not _below_) the page content
		// @see https://phabricator.wikimedia.org/T367305
		// @note Using getText() instead of getInt() because it can also have the non-int values "cur", "next" or "prev"
		$diff = $request->getText( 'diff' );
		if ( $diff ) {
			parent::view();
			// Respect the user preference option for those users who have enabled it
			$diffOnly = $services->getUserOptionsLookup()->getBoolOption( $user, 'diffonly' );
			if ( $diffOnly ) {
				return;
			}
		}

		// Support viewing older, non-current versions of the UserBox in question
		// @see https://phabricator.wikimedia.org/T367802
		$oldId = $request->getInt( 'oldid' );
		$direction = $request->getText( 'direction' );
		if ( $oldId ) {
			if ( $direction || ( $diff && !is_numeric( $diff ) ) ) {
				$lookupService = $services->getRevisionLookup();
				$currentRevision = $lookupService->getRevisionById( $oldId );
				$oldId = $currentRevision->getId();

				if ( $direction === 'prev' || $diff === 'prev' ) {
					$oldId = $lookupService->getPreviousRevision( $currentRevision )->getId();
				} elseif ( $direction === 'next' || $diff === 'next' ) {
					$oldId = $lookupService->getNextRevision( $currentRevision )->getId();
				}
			}

			// For URLs like http://localhost/139/index.php?title=UserBox:Foo&diff=2601&oldid=2597 , prefer the revision ID
			// specified as the "diff" param over the "oldid" one
			if ( is_numeric( $diff ) ) {
				$oldId = $diff;
			}

			// @todo FIXME: newFromOldId *can* return null on failure!
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Suppress this for the time being...
			$this->fan = FanBox::newFromOldId( $oldId );
		} else {
			$this->fan = new FanBox( $this->getTitle() );
		}

		// Check if the diff param is "cur" since that means "we're comparing an older revision
		// to the current one" and in that case, we want to display the _current_ UserBox contents,
		// i.e. the right-hand side of a diff, not the old (left-hand side) contents
		if ( $diff === 'cur' ) {
			$this->fan = new FanBox( $this->getTitle() );
		}

		$output = '';

		$output .= '<div class="fanbox-page-container clearfix">' .
			$this->fan->outputFanBox();
		$fantag_id = $this->fan->getFanBoxId();

		$output .= '<div id="show-message-container' . $fantag_id . '">';

		if ( $user->isRegistered() ) {
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
				<h2>' . wfMessage( 'fanbox-users-with-fanbox' )->escaped() . '</h2>
				<div class="users-with-fanbox-message">' .
					wfMessage( 'fanbox-users-with-fanbox-message' )->escaped() .
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

		if ( !$diff ) {
			parent::view();
		}
	}

	/**
	 * Get the users who have the current fanbox.
	 *
	 * @return array array containing the users' names and IDs or an empty
	 *                array
	 */
	function getFanBoxHolders() {
		$pageTitleId = $this->getTitle()->getArticleID();

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
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
	 * @return string HTML
	 */
	function fanBoxHolders() {
		$output = '';
		$fanboxHolders = $this->getFanBoxHolders();
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		foreach ( $fanboxHolders as $fanboxHolder ) {
			$actor = $userFactory->newFromActorId( $fanboxHolder['userft_actor'] );
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
	 * @return string HTML
	 */
	public function getEmbedThisTag() {
		$code = $this->fan->getEmbedThisCode();
		$code = preg_replace( '/[\n\r\t]/', '', $code ); // remove any non-space whitespace
		$code = str_replace( '_', ' ', $code ); // replace underscores with spaces
		$code = htmlspecialchars( $code, ENT_QUOTES );
		return '<form name="embed_fan" action="">' . wfMessage( 'fanbox-embed' )->escaped() .
			" <input name='embed_code' type='text' value='{$code}' onclick='document.embed_fan.embed_code.focus();document.embed_fan.embed_code.select();' readonly='readonly' /></form>";
	}

}
