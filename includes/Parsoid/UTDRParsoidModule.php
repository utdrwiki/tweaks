<?php

namespace MediaWiki\Extension\UTDRTweaks\Parsoid;

use Wikimedia\Parsoid\Ext\ExtensionModule;

class UTDRParsoidModule implements ExtensionModule {
	public function getConfig(): array {
		return [
			'name' => 'UTDRTweaks',
			'domProcessors' => [ UTDRDOMProcessor::class ],
		];
	}
}
