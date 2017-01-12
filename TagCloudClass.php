<?php
/**
 * @copyright Copyright © 2007, Wikia Inc.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class TagCloud {
	public $tags_min_pts = 8;
	public $tags_max_pts = 32;
	public $tags_highest_count = 0;
	public $tags_size_type = 'pt';
	public $tags = array();

	public function __construct( $limit = 10 ) {
		$this->limit = $limit;
		$this->initialize();
	}

	public function initialize() {
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			'category',
			array( 'cat_title', 'cat_pages' ),
			array(),
			__METHOD__,
			array(
				'ORDER BY' => 'cat_pages DESC',
				'LIMIT' => $this->limit
			)
		);
		MediaWiki\suppressWarnings(); // prevent PHP from bitching about strtotime()
		foreach ( $res as $row ) {
			$tag_name = Title::makeTitle( NS_CATEGORY, $row->cat_title );
			$tag_text = $tag_name->getText();
			if ( strtotime( $tag_text ) == '' ) { // don't want dates to show up
				if ( $row->cat_pages > $this->tags_highest_count ) {
					$this->tags_highest_count = $row->cat_pages;
				}
				$this->tags[$tag_text] = array( 'count' => $row->cat_pages );
			}
		}
		MediaWiki\restoreWarnings();

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