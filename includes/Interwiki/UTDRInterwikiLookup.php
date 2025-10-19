<?php

namespace MediaWiki\Extension\UTDRTweaks\Interwiki;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * ClassicInterwikiLookup does not fetch interwiki prefixes from virtual
 * domains. This causes ApiQuerySiteinfo to not return global interwiki
 * prefixes, which in turn makes Parsoid not recognize such prefixes during
 * parsing. This subclass adds that functionality.
 */
class UTDRInterwikiLookup extends ClassicInterwikiLookup {
	/**
	 * @inheritDoc
	 */
	public function __construct(
		private readonly ServiceOptions $options,
		Language $contLang,
		WANObjectCache $wanCache,
		HookContainer $hookContainer,
		private readonly IConnectionProvider $dbProvider,
		private readonly LanguageNameUtils $languageNameUtils
	) {
		parent::__construct(
			$options,
			$contLang,
			$wanCache,
			$hookContainer,
			$dbProvider,
			$languageNameUtils
		);
	
	}
	/**
	 * @inheritDoc
	 */
	public function getAllPrefixes( $local = null ) {
		$data = parent::getAllPrefixes( $local );
		if ( $local === true ) {
			return $data;
		}
		return array_merge(
			$data,
			$this->getPrefixesFromVirtualDomain( 'virtual-interwiki', false ),
			$this->getPrefixesFromVirtualDomain( 'virtual-interwiki-interlanguage', true ),
		);
	}
	/**
	 * Retrieves interwiki prefixes from a virtual domain.
	 * @param string $virtualDomain Virtual domain
	 * @param bool $shouldBeLanguage Whether to filter for language interwikis
	 * @return array[]
	 * @see SpecialInterwiki::showList()
	 */
	private function getPrefixesFromVirtualDomain( string $virtualDomain, bool $shouldBeLanguage ): array {
		$virtualDomainsMapping = $this->options->get( MainConfigNames::VirtualDomainsMapping );
		if ( !isset( $virtualDomainsMapping[$virtualDomain] ) ) {
			return [];
		}
		$prefixes = [];
		$result = $this->dbProvider
			->getReplicaDatabase( $virtualDomain )
			->newSelectQueryBuilder()
			->select( '*' )
			->from( 'interwiki' )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $result as $row ) {
			$row = (array)$row;
			$isLanguage = $this->options->get( MainConfigNames::InterwikiMagic ) &&
			              $this->languageNameUtils->getLanguageName( $row['iw_prefix'] );
			if ( $isLanguage === $shouldBeLanguage ) {
				$prefixes[] = $row;
			}
		}
		return $prefixes;
	}
}

