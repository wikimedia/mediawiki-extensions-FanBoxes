<?php

/**
 * Quick helper class for SpecialFanBoxAjaxUpload::loadRequest; this prefixes
 * the filename with the timestamp. Yes, another class is needed for it. *sigh*
 */
class FanBoxUpload extends UploadFromFile {
	/**
	 * Create a form of UploadBase depending on wpSourceType and initializes it
	 * @param WebRequest &$request
	 * @param string|null $type
	 * @return self
	 */
	public static function createFromRequest( &$request, $type = null ) {
		$handler = new self;
		$handler->initializeFromRequest( $request );
		return $handler;
	}

	/**
	 * @param WebRequest &$request
	 * @return void
	 */
	function initializeFromRequest( &$request ) {
		$upload = $request->getUpload( 'wpUploadFile' );

		$desiredDestName = $request->getText( 'wpDestFile' );
		if ( !$desiredDestName ) {
			$desiredDestName = $request->getFileName( 'wpUploadFile' );
		}
		$desiredDestName = time() . '-' . $desiredDestName;

		$this->initialize( $desiredDestName, $upload );
	}

	/** @inheritDoc */
	public function doStashFile( ?User $user = null ) {
		return parent::doStashFile( $user );
	}
}
