<?php
namespace MediaWiki\Extension\UTDRTweaks;

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
}
