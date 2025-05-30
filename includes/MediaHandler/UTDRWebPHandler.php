<?php
namespace MediaWiki\Extension\UTDRTweaks\MediaHandler;

use MediaWiki\Extension\Thumbro\MediaHandlers\ThumbroWebPHandler;

class UTDRWebPHandler extends ThumbroWebPHandler {
	/**
	 * @inheritDoc
	 */
	public function canRender( $file ) {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function canAnimateThumbnail( $file ) {
		return true;
	}
}
