<?php
namespace MediaWiki\Extension\UTDRTweaks\FileRepo;

use MediaWiki\FileRepo\File\ForeignDBFile;

/**
 * Custom ForeignDBFile class adding cache buster parameters to file URLs.
 */
class UTDRForeignDBFile extends ForeignDBFile {
	use CachedFileTrait;

	/**
	 * Constructs a regular LocalFile object, but using our custom repo class.
	 * @param mixed $title
	 * @param mixed $repo
	 */
	public function __construct( $title, $repo ) {
		$this->repoClass = UTDRForeignDBRepo::class;
		parent::__construct( $title, $repo );
	}
}
