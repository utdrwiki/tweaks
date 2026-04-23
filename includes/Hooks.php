<?php
namespace MediaWiki\Extension\UTDRTweaks;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\UTDRTweaks\Interwiki\UTDRInterwikiLookup;
use MediaWiki\Extension\UTDRTweaks\Preferences\UTDRPreferencesFactory;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Hook\ImageBeforeProduceHTMLHook;
use MediaWiki\Html\Html;
use MediaWiki\Interwiki\ClassicInterwikiLookup;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Skin\Skin;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class Hooks implements ImageBeforeProduceHTMLHook {
	private const CAPTION_REQUIRED_MEDIA_TYPES = [
		MEDIATYPE_BITMAP,
		MEDIATYPE_DRAWING,
		MEDIATYPE_VIDEO,
	];

	/**
	 * Moves all notifications to the 'alert' section, because our skin only
	 * displays that section.
	 * @param array $notifications Value of $wgEchoNotifications
	 * @param array $notificationCategories Value of $wgEchoNotificationCategories
	 * @param array $notificationIcons Value of $wgEchoNotificationIcons
	 * @return void
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$notificationIcons
	) {
		foreach ( $notifications as &$notificationConfig ) {
			$notificationConfig['section'] = 'alert';
		}
	}

	/**
	 * Adds a checkbox to the user registration form to accept the Terms of Service.
	 * @param \MediaWiki\Auth\AuthenticationRequest[] $requests Array of AuthenticationRequests the fields are created from
	 * @param array $fieldInfo Field information array
	 * @param array $formDescriptor HTMLForm descriptor. The special key 'weight' can be set to change the order of the fields.
	 * @param string $action One of the AuthManager::ACTION_* constants
	 * @return void
	 */
	public static function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ): void {
		if ( $action !== AuthManager::ACTION_CREATE ) {
			return;
		}
		$formDescriptor['tos'] = [
			'type' => 'check',
			'label-message' => 'utdr-accept-tos',
			'name' => 'wpTos',
			'id' => 'wpTos',
			'required' => true,
		];
	}

	/**
	 * Replace footer with only Terms of Service and Privacy Policy links.
	 * @param Skin $skin Skin object
	 * @param string $key Key of the footer link
	 * @param array $footerlinks Array of footer links
	 */
	public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerlinks ): void {
		if ( $key === 'places' ) {
			$footerlinks = [
				'terms-of-service' => Html::element( 'a', [
					'href' => 'https://undertale.wiki/w/Undertale_Wiki:Terms_of_Service',
				], $skin->msg( 'utdr-terms-of-service' )->text() ),
				'privacy-policy' => Html::element( 'a', [
					'href' => 'https://undertale.wiki/w/Undertale_Wiki:Privacy_Policy',
				], $skin->msg( 'utdr-privacy-policy' )->text() ),
			];
		}
	}

	/**
	 * Fall back to a file's page title whenever an alt text is missing.
	 * @param $unused Will always be null
	 * @param Title &$title Title object of the image
	 * @param File|false &$file File object, or false if it doesn't exist
	 * @param array &$frameParams Various parameters with special meanings; see documentation in
	 *   includes/Linker.php for Linker::makeImageLink
	 * @param array &$handlerParams Various parameters with special meanings; see documentation in
	 *   includes/Linker.php for Linker::makeImageLink
	 * @param string|bool &$time Timestamp of file in 'YYYYMMDDHHIISS' string
	 *   form, or false for current
	 * @param string &$res Final HTML output, used if you return false
	 * @param Parser $parser
	 * @param string &$query Query params for desc URL
	 * @param string &$widthOption Used by the parser to remember the user preference thumbnailsize
	 */
	public function onImageBeforeProduceHTML( $unused, &$title, &$file,
		&$frameParams, &$handlerParams, &$time, &$res, $parser, &$query, &$widthOption ): void {
		if ( !isset( $frameParams['alt'] ) && $file ) {
			$title = $file->getTitle();
			$frameParams['alt'] = $title ? $title->getText() : "";
		}
		if (
			empty( $frameParams['caption'] ) &&
			$file &&
			in_array( $file->getMediaType(), self::CAPTION_REQUIRED_MEDIA_TYPES )
		) {
			$parser->addTrackingCategory( 'utdr-category-pages-without-captions' );
		}
	}

	/**
	 * Changes the selflink to a span instead of a link, since it's not actually
	 * a link.
	 * @param \MediaWiki\Title\Title $nt Title object that the link leads to
	 * @param mixed $html HTML output of the link
	 * @param mixed $trail Trailing HTML
	 * @param mixed $prefix Prefix HTML
	 * @param mixed $ret Return value
	 * @return bool False to stop processing
	 */
	public static function onSelfLinkBegin( Title $nt, &$html, &$trail, &$prefix, &$ret ): bool {
		$ret = Html::rawElement( 'span', [
			'class' => 'mw-selflink selflink'
		], $prefix . $html ) . $trail;
		return false;
	}


	/**
	 * Marks user pages and talk pages as always known, so that they show up
	 * as blue links instead of red links.
	 * @param \MediaWiki\Title\Title $title Title object that is being checked
	 * @param bool|null &$isKnown Whether MediaWiki currently thinks this page is known
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onTitleIsAlwaysKnown( $title, &$isKnown ) {
		if ( $title->isTalkPage() || $title->inNamespace( NS_USER ) ) {
			$isKnown = true;
		}
	}

	/**
	 * Don't count talk pages as wanted.
	 * @param \MediaWiki\Specials\SpecialWantedPages $wantedPages
	 * @param array &$query Query array. See QueryPage::getQueryInfo() for format documentation.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onWantedPages__getQueryInfo( &$wantedPages, &$query ) {
		$query['conds'][] = 'lt_namespace % 2 = 0';
	}

	/**
	 * Turn more URLs into fancy URLs.
	 * Taken from GloopTweaks, by TehKittyCat and Jayden.
	 */
	public static function onGetLocalURL( $title, &$url, $query ) {
		global $wgArticlePath, $wgScript;
		$dbkey = wfUrlencode( $title->getPrefixedDBkey() );
		if ( $url == "{$wgScript}?title={$dbkey}&{$query}" ) {
			$url = wfAppendQuery( str_replace( '$1', $dbkey, $wgArticlePath ), $query );
		}
	}

	/**
	 * Remove "== Summary ==" from initial file page text.
	 * @param string $pageText
	 * @param array $msg Array of header messages
	 * @param Config $config
	 * @return void
	 */
	public static function onUploadForm_getInitialPageText( string &$pageText, array $msg, Config $config ) {
		$pageText = str_replace(
			"== {$msg['filedesc']} ==\n",
			'',
			$pageText
		);
	}

	/**
	 * Remove Bucket's sidebar link.
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$sidebar['TOOLBOX'] = array_filter(
			$sidebar['TOOLBOX'],
			fn( $item ) => !isset( $item['id'] ) || $item['id'] !== 'n-bucket'
		);
	}

	/**
	 * Purges the recent changes URLs used by the sidebar gadget on the CDN.
	 * @param Title $title The title of the page that was updated
	 * @param int $mode Whether this was a result of a LinksUpdate
	 * @param array &$urls Array of URLs to be purged
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/HtmlCacheUpdaterAppendUrls
	 */
	public static function onHtmlCacheUpdaterAppendUrls( Title $title, int $mode, array &$urls ): void {
		if ( $title->getNamespace() === NS_MAIN && $mode === 0 ) {
			global $wgServer, $wgScriptPath;
			$urls[] = "$wgServer$wgScriptPath/api.php?action=query&format=json&list=recentchanges&rcprop=title%7Cids%7Cuser%7Cuserid%7Ctimestamp&rclimit=50&rcshow=!bot&rctype=new%7Cedit&rcnamespace=0";
			$urls[] = "$wgServer$wgScriptPath/api.php?action=query&format=json&generator=recentchanges&grcnamespace=0&grclimit=50&grcshow=!bot&prop=pageimages%7Cinfo&inprop=displaytitle";
		}
	}

	public function onMediaWikiServices( MediaWikiServices $services ): void {
		$services->redefineService( 'InterwikiLookup', fn (
			MediaWikiServices $services
		): InterwikiLookup => new UTDRInterwikiLookup(
			new ServiceOptions(
				ClassicInterwikiLookup::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
				[ 'wikiId' => WikiMap::getCurrentWikiId() ],
			),
			$services->getContentLanguage(),
			$services->getMainWANObjectCache(),
			$services->getHookContainer(),
			$services->getConnectionProvider(),
			$services->getLanguageNameUtils()
		) );
		$services->redefineService( 'PreferencesFactory', fn (
			MediaWikiServices $services
		): PreferencesFactory => new UTDRPreferencesFactory(
			$services->getMainConfig()->get( 'UTDRSupportedLanguages' ),
			new ServiceOptions(
				DefaultPreferencesFactory::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getContentLanguage(),
			$services->getAuthManager(),
			$services->getLinkRendererFactory()->create(),
			$services->getNamespaceInfo(),
			$services->getPermissionManager(),
			$services->getLanguageConverterFactory()->getLanguageConverter(),
			$services->getLanguageNameUtils(),
			$services->getHookContainer(),
			$services->getUserOptionsManager(),
			$services->getLanguageConverterFactory(),
			$services->getParserFactory(),
			$services->getSkinFactory(),
			$services->getUserGroupManager(),
			$services->getSignatureValidatorFactory()
		) );
	}

	public function onEditFilterMergedContent( IContextSource $context, Content $content, Status $status, string $summary, User $user, bool $minoredit ): bool {
		$blockedEmailRegex = $context->getConfig()->get( 'UTDRBlockedEmailRegex' );
		if ( !$blockedEmailRegex || !preg_match( $blockedEmailRegex, $user->getEmail() ) ) {
			return true;
		}
		$status->fatal( 'abusefilter-disallowed', 'Vandalism' );
		$services = MediaWikiServices::getInstance();
		$services->getBlockUserFactory()->newBlockUser(
			$user,
			$services->getService( FilterUser::SERVICE_NAME )->getAuthority(),
			'infinite',
			'Vandalism',
			[
				'isHardBlock' => true,
				'isAutoblocking' => true,
				'isCreateAccountBlocked' => true,
				'isUserTalkEditBlocked' => true,
			]
		)->placeBlockUnsafe();
		return false;
	}
}
