<?php
namespace MediaWiki\Extension\UTDRTweaks;

use MediaWiki\Auth\AuthManager;

class Hooks {
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
}
