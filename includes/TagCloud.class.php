<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\AtEase\AtEase;

/**
 * @copyright Copyright © 2007, Wikia Inc.
 * @license GPL-2.0-or-later
 */
class TagCloud {
	/** @var int */
	public $tags_min_pts = 8;
	/** @var int */
	public $tags_max_pts = 32;
	/** @var int */
	public $tags_highest_count = 0;
	/** @var array[] */
	public $tags = [];
	/** @var int */
	public $limit;

	/**
	 * @param int $limit
	 */
	public function __construct( $limit = 10 ) {
		$this->limit = $limit;
		$this->initialize();
	}

	public function initialize() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$res = $dbr->select(
			'category',
			[ 'cat_title', 'cat_pages' ],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'cat_pages DESC',
				'LIMIT' => $this->limit
			]
		);
		AtEase::suppressWarnings(); // prevent PHP from bitching about strtotime()
		foreach ( $res as $row ) {
			$tag_name = Title::makeTitle( NS_CATEGORY, $row->cat_title );
			$tag_text = $tag_name->getText();
			if ( strtotime( $tag_text ) == '' ) { // don't want dates to show up
				if ( $row->cat_pages > $this->tags_highest_count ) {
					$this->tags_highest_count = $row->cat_pages;
				}
				$this->tags[$tag_text] = [ 'count' => $row->cat_pages ];
			}
		}
		AtEase::restoreWarnings();

		// sort tag array by key (tag name)
		if ( $this->tags_highest_count == 0 ) {
			return;
		}
		ksort( $this->tags );
		/* and what if we have _1_ category? like on a new wiki with nteen articles, mhm? */
		if ( $this->tags_highest_count == 1 ) {
			$coef = $this->tags_max_pts - $this->tags_min_pts;
		} else {
			$coef = ( $this->tags_max_pts - $this->tags_min_pts ) / ( ( $this->tags_highest_count - 1 ) * 2 );
		}
		foreach ( $this->tags as $tag => $att ) {
			$this->tags[$tag]['size'] = $this->tags_min_pts + ( $this->tags[$tag]['count'] - 1 ) * $coef;
		}
	}
}
