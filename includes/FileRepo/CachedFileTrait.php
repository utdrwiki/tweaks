<?php

namespace MediaWiki\Extension\UTDRTweaks\FileRepo;

trait CachedFileTrait {
	/**
	 * Adds a suffix to an image/thumbnail URL for cache busting. When a new
	 * file is uploaded, the SHA1 hash of the file changes, and users should
	 * get the new version of the file instead.
	 *
	 * Because MediaWiki's mw.util.parseImageUrl function is extremely stupid
	 * and doesn't work properly after we add this cache buster, MediaViewer
	 * stops displaying some of the images on the wiki. So to do this, we add
	 * two more query parameters that make that function think we're actually
	 * using a thumb.php URL and correctly parse the filename.
	 *
	 * @param string $url URL to the file's image
	 * @return string
	 */
	private function addCacheBuster( string $url ): string {
		return wfAppendQuery( $url, [
			'cb' => substr( $this->getSha1(), 0, 6 ),
			// This works because mw.util.parseImageUrl takes a different code
			// path when the URL matches /thumb\.php/.
			'h' => 'thumb.php',
			'f' => $this->getName(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl() {
		return $this->addCacheBuster( parent::getUrl() );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbUrl( $suffix = false ) {
		return $this->addCacheBuster( parent::getThumbUrl( $suffix ) );
	}
}
