<?php
/**
 * This extension leverages the CachingPolicy's ability to customise the max-age per originator.
 * The configuration option is surfaced to the CMS UI. The extension needs to be added
 * to the object related to the policed controller.
 */
class PageControlledPolicy extends DataExtension {

	private static $db = array(
		'MaxAge' => 'Varchar'
	);

	/**
	 * Extension point for the CachingPolicy.
	 */
	public function getCacheAge($cacheAge) {
		if ($this->owner->MaxAge!='') {
			return (int)($this->owner->MaxAge*60);
		}
	}

	public function updateCMSFields(FieldList $fields) {
		// Only admins are allowed to modify this.
		$member = Member::currentUser();
		if (!$member || !Permission::checkMember($member, 'ADMIN')) {
			return;
		}

		$fields->addFieldsToTab('Root.Caching', array(
			new LiteralField('Instruction', '<p>The following field controls the length of time the page will ' .
				'be cached for. You will not be able to see updates to this page for at most the specified ' .
				'amount of minutes. Leave empty to set back to the default configured for your site. Set ' .
				'to 0 to explicitly disable caching for this page.</p>'),
			new TextField('MaxAge', 'Custom cache timeout [minutes]')
		));
	}

}
