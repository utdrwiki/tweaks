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

	/**
	 * MediaWiki's FileSelectQueryBuilder queries the actor table for the wiki
	 * that the file belongs to. This is a problem on Deltarune Wiki, because
	 * our actor table is shared and resides on the Undertale Wiki instead.
	 * @return callable New database factory
	 */
	protected function getDBFactory() {
		$parentFunc = parent::getDBFactory();
		return static function ( $index ) use ( $parentFunc ) {
			$db = $parentFunc( $index );
			$db->setTableAliases( [
				'actor' => [
					'dbname' => 'en_undertale',
					'schema' => '',
					'prefix' => '',
				]
			] );
			return $db;
		};
	}
}
