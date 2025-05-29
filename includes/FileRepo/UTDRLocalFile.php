<?php
namespace MediaWiki\Extension\UTDRTweaks\FileRepo;

use LocalFile;

/**
 * Custom LocalFile class adding cache buster parameters to file URLs.
 */
class UTDRLocalFile extends LocalFile {

	/**
	 * Constructs a regular LocalFile object, but using our custom repo class.
	 * @param mixed $title
	 * @param mixed $repo
	 */
	public function __construct( $title, $repo ) {
		$this->repoClass = UTDRLocalRepo::class;
		parent::__construct( $title, $repo );
	}

	/**
	 * Adds a suffix to an image/thumbnail URL for cache busting. When a new
	 * file is uploaded, the SHA1 hash of the file changes, and users should
	 * get the new version of the file instead.
	 * @return string
	 */
	private function addCacheBuster( string $url ): string {
		return wfAppendQuery( $url, [
			'cb' => substr( $this->getSha1(), 0, 6 )
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
