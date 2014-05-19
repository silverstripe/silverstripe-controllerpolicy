<?php
/**
 * This request filter accepts registrations of policies to be applied at the end of the control pipeline.
 * The policies will be applied in the order they are added, and will override HTTP::add_cache_headers.
 */

class ControllerPolicyRequestFilter implements RequestFilter {

	/**
	 * An associative array containing the 'originator' and 'policy' reference.
	 */
	private $requestedPolicies = array();

	/**
	 * Add a policy tuple.
	 */
	public function requestPolicy($originator, $policy) {
		$this->requestedPolicies[] = array('originator' => $originator, 'policy' => $policy);
	}

	public function clearPolicies() {
		$this->requestedPolicies = array();
	}

	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {

		// No-op, we don't know the controller at this stage.
		return true;

	}

	/**
	 * Apply all the requested policies.
	 */
	public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		foreach ($this->requestedPolicies as $requestedPolicy) {

			$requestedPolicy['policy']->applyToResponse(
				$requestedPolicy['originator'],
				$request,
				$response,
				$model
			);

		}

		return true;

	}

}
