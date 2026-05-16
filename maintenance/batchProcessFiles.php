<?php

namespace MediaWiki\Extension\UTDRTweaks\Maintenance;

use MediaWiki\FileRepo\File\FileSelectQueryBuilder;
use MediaWiki\FileRepo\File\LocalFile;
use MediaWiki\FileRepo\LocalRepo;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Shell\CommandFactory;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function count;
use function Wikimedia\base_convert;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Processes all files of the wiki based on specific selection criteria
 * (minimum and maximum file size, MIME type, etc.) Backs up the file before
 * processing, and if the file has changed during processing, updates the SHA1
 * hash in the database.
 *
 * Currently the only batch processing operation available is image
 * optimization using the `optipng -o5 -strip all [image]` command.
 */
class BatchProcessFiles extends Maintenance {
	private LocalRepo $repo;
	private CommandFactory $cmdFactory;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'A maintenance script for batch processing files based on selection criteria.' );
		$this->setBatchSize( 1000 );
		$this->addArg(
			'operation',
			'The batch operation to perform on the files (e.g., "optimize")',
			true
		);
		$this->addOption(
			'dry-run',
			'If set, the script will only simulate the operations without making any changes'
		);
		$this->addOption(
			'min-size',
			'Minimum file size (in bytes) to be processed',
			false,
			true
		);
		$this->addOption(
			'max-size',
			'Maximum file size (in bytes) to be processed',
			false,
			true
		);
		$this->addOption(
			'mime-type',
			'MIME type to be processed',
			false,
			true
		);
	}

	public function execute() {
		$operation = $this->getArg( 'operation' );
		$dryRun = $this->getOption( 'dry-run', false );
		$minSize = $this->getOption( 'min-size', 0 );
		$maxSize = $this->getOption( 'max-size', 1 * 1024 * 1024 );
		$mimeType = $this->getOption( 'mime-type' );

		$services = $this->getServiceContainer();
		$this->repo = $services->getRepoGroup()->getLocalRepo();
		$this->cmdFactory = $services->getShellCommandFactory();
		$logger = LoggerFactory::getInstance( 'BatchProcessFiles' );
		$this->cmdFactory->setLogger( $logger );

		// Create a backup directory if it doesn't exist.
		$backupDir = wfTempDir() . '/file_backup';
		if ( is_dir( $backupDir ) ) {
			wfRecursiveRemoveDir( $backupDir );
		}
		if ( !@mkdir( $backupDir, 0777, true ) ) {
			$this->fatalError( "Failed to create backup directory: $backupDir\n" );
		}

		// Build the selection criteria.
		$dbr = $this->getReplicaDB();
		$start = '';
		$conds = [
			$dbr->expr( 'img_name', '>', '' ),
			$dbr->expr( 'img_size', '>=', $minSize ),
			$dbr->expr( 'img_size', '<=', $maxSize ),
		];
		if ( $mimeType ) {
			$parts = explode( '/', $mimeType );
			if ( count( $parts ) !== 2 ) {
				$this->fatalError( "Invalid MIME type format: $mimeType\n" );
			}
			[ $major, $minor ] = $parts;
			$conds[] = $dbr->expr( 'img_major_mime', '=', $major );
			$conds[] = $dbr->expr( 'img_minor_mime', '=', $minor );
		}

		// Process files in batches.
		$numChanged = 0;
		do {
			$conds[0] = $dbr->expr( 'img_name', '>', $start );
			$res = FileSelectQueryBuilder::newForFile( $dbr )
				->where( $conds )
				->limit( $this->getBatchSize() )
				->orderBy( 'img_name', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__)
				->fetchResultSet();
			foreach ( $res as $row ) {
				$start = $row->img_name;
				$file = $this->repo->newFileFromRow( $row );
				switch ( $operation ) {
					case 'optimize':
						$changed = $this->optimize( $file, $backupDir, $dryRun );
						break;
					case 'fix-filesize':
						$changed = $this->fixFileSize( $file, $dryRun );
						break;
					default:
						$this->fatalError( "Unsupported operation: $operation\n" );
				}
				if ( $changed ) {
					++$numChanged;
				}
			}
		} while ( $res->numRows() );

		$this->output( "Files changed: $numChanged\n" );
	}

	private function optimize( LocalFile $file, string $backupDir, bool $dryRun ): bool {
		$fileName = $file->getName();
		$this->output( "Processing $fileName...\n" );
		$name = $file->getName();
		$path = $file->getPath();
		$tempFilePath = preg_replace(
			'/[\\\\&!?"<>%^{};,*[\\]]/',
			'__',
			"$backupDir/$name",
		);
		$size = $this->repo->getFileSize( $path );
		if ( $size === false ) {
			$this->output( "$fileName does not exist.\n" );
			return false;
		}
		if ( $size != $file->getSize() ) {
			$this->output( "$fileName size has changed since selection; skipping.\n" );
			return false;
		}
		$oldSha1 = $file->getSha1();
		$filePath = $file->getLocalRefPath();
		$result = $this->cmdFactory
			->createBoxed( 'UTDRTweaks' )
			->params(
				'optipng',
				'-o5',
				'-strip=all',
				'-preserve',
				'-fix',
				'-out=output.png',
				'--',
				'input.png',
			)
			->inputFileFromFile( 'input.png', $filePath )
			->outputFileToFile( 'output.png', $tempFilePath )
			->firejailDefaultSeccomp()
			->disableNetwork()
			->routeName( 'UTDRTweaks-optipng' )
			->execute();
		if ( $result->getExitCode() !== 0 ) {
			$this->error( "Failed to process $fileName:\nout: {$result->getStdout()}\nerr: {$result->getStderr()}\n" );
			return false;
		}
		$newSha1 = base_convert( sha1_file( $tempFilePath ), 16, 36, 31 );
		if ( $oldSha1 === $newSha1 ) {
			$this->output( "$fileName unchanged after processing; skipping update.\n" );
			@unlink( $tempFilePath );
			return false;
		}
		$newSize = filesize( $tempFilePath );
		if ( !$dryRun ) {
			// TODO: Update for the new file table schema.
			$this->getPrimaryDB()
				->newUpdateQueryBuilder()
				->update( 'image' )
				->set( [
					'img_sha1' => $newSha1,
					'img_size' => $newSize,
				] )
				->where([ 'img_name' => $fileName ])
				->caller( __METHOD__ )
				->execute();
			$status = $this->repo->quickImport( $tempFilePath, $path );
			if ( !$status->isGood() ) {
				$this->error( $status );
				return false;
			}
		}
		return true;
	}

	private function fixFileSize( LocalFile $file, bool $dryRun ): bool {
		$oldSize = $file->getSize();
		$newSize = $this->repo->getFileSize( $file->getPath() );
		if ( $oldSize === $newSize ) {
			return false;
		}
		$this->output( "Fixing file size for {$file->getName()}...\n" );
		if ( !$dryRun ) {
			$this->getPrimaryDB()
				->newUpdateQueryBuilder()
				->update( 'image' )
				->set( [ 'img_size' => $newSize ] )
				->where( [ 'img_name' => $file->getName() ] )
				->caller( __METHOD__ )
				->execute();
		}
		return true;
	}
}

$maintClass = BatchProcessFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
