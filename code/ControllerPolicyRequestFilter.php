<?php
/**
 * This request filter accepts registrations of policies to be applied at the end of the control pipeline.
 * The policies will be applied in the order they are added, and will override HTTP::add_cache_headers.
 */

class ControllerPolicyRequestFilter implements RequestFilter {

	private $policies = array();

	public function addPolicy($policy) {
		$this->policies[] = $policy;
	}

	public function clearPolicies() {
		$this->policies = array();
	}

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		// No-op, we don't know the controller at this stage.
		return true;

	}

	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		foreach ($this->policies as $policy) {
			$policy->applyToResponse($request, $response, $model);
		}

		return true;

	}

}
