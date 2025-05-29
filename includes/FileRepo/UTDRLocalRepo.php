<?php
namespace MediaWiki\Extension\UTDRTweaks\FileRepo;

use LocalRepo;

/**
 * Custom LocalRepo class adding cache buster parameters to file URLs.
 */
class UTDRLocalRepo extends LocalRepo {
	public function __construct( array $info = null ) {
		$this->fileFactory = [ UTDRLocalFile::class, 'newFromTitle' ];
		$this->fileFactoryKey = [ UTDRLocalFile::class, 'newFromKey' ];
		$this->fileFromRowFactory = [ UTDRLocalFile::class, 'newFromRow' ];
		parent::__construct( $info );
	}
}
