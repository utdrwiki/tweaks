<?php

namespace MediaWiki\Extension\UTDRTweaks\Parsoid;

use MediaWiki\Title\Title;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Utils\DOMCompat;

class UTDRDOMProcessor extends DOMProcessor {
	public function wtPostprocess(
		ParsoidExtensionAPI $api, Node $node, array $opts
	): void {
		$this->addAltAttributes( $node );
	}

	private function addAltAttributes( Node $rootNode ): void {
		foreach ( DOMCompat::getElementsByTagName( $rootNode, 'img' ) as $imgNode ) {
			if ( DOMCompat::getAttribute( $imgNode, 'alt' ) !== null ) {
				continue;
			}
			$resource = DOMCompat::getAttribute( $imgNode, 'resource' );
			if ( empty( $resource ) ) {
				continue;
			}
			$filePage = rawurldecode( str_replace( './', '', $resource ) );
			$fileTitle = Title::newFromText( $filePage );
			if ( !$fileTitle || !$fileTitle->inNamespace( NS_FILE ) ) {
				continue;
			}
			DOMUtils::addAttributes( $imgNode, [
				'alt' => $fileTitle->getText(),
			] );
		}
	}
}
