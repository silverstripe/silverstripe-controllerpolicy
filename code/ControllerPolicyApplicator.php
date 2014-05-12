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

	private $requestFilter;

	function setRequestFilter($filter) {
		$this->requestFilter = $filter;
	}

	/**
	 * $policy injected to $this->owner
	 */

	function setPolicy($policy) {
		$this->owner->policy = $policy;
	}

	function getPolicy() {
		if (isset($this->owner) && isset($this->owner->policy)) {
			return $this->owner->policy;
		}
	}

	/**
	 * Register the requested policy with the global request filter. This doesn't mean the policy will be
	 * executed at this point - it will rather be delayed until the RequestProcessor::postRequest runs.
	 */
	function onAfterInit() {

		if ($this->getPolicy()) {
			$this->requestFilter->addPolicy($this->getPolicy());
		}

	}

}
