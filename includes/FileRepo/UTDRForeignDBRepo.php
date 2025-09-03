<?php
namespace MediaWiki\Extension\UTDRTweaks\FileRepo;

use MediaWiki\FileRepo\ForeignDBRepo;

/**
 * Custom ForeignDBRepo class adding cache buster parameters to file URLs.
 */
class UTDRForeignDBRepo extends ForeignDBRepo {
	public function __construct( array $info ) {
		$info['dbFlags'] = DBO_DEFAULT;
		$info['tablePrefix'] = '';
		$this->fileFactory = [ UTDRForeignDBFile::class, 'newFromTitle' ];
		$this->fileFactoryKey = [ UTDRForeignDBFile::class, 'newFromKey' ];
		$this->fileFromRowFactory = [ UTDRForeignDBFile::class, 'newFromRow' ];
		parent::__construct( $info );
	}
}
