<?php
/**
 * This extension will register the policy with the RequestProcessor filter system to be run at postRequest stage
 * of the control pipeline. This is done with the help of the ControllerPolicyRequestFilter.
 *
 * This will override any specific headers that have been set by the default HTTP::add_cache_headers, which is
 * actually what we want. The policies are applied in the order they are added, so if there are two added the
 * latter will override the former.
 */
class ControllerPolicyApplicator extends Extension {
	
	private $policy;

	function setPolicy($policy) {
		$this->policy = $policy;
	}

	function getPolicy() {
		return $this->policy;
	}

	/**
	 * Sets up the policy pseudo-singleton from the class name.
	 */
	function __construct($policyClass) {
		$this->setPolicy(Injector::inst()->get($policyClass));
	}

	/**
	 * Register the requested policy with the global request filter. This doesn't mean the policy will be
	 * executed at this point - it will rather be delayed until the RequestProcessor::postRequest runs.
	 */
	function onAfterInit() {

		if ($this->getPolicy()) {
			$requestFilter = Injector::inst()->get('ControllerPolicyRequestFilter');
			$requestFilter->addPolicy($this->getPolicy());
		}

	}

}
