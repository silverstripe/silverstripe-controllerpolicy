<?php
/**
 * This policy can be used as a writing any http header value.
 *
 * Configuration:
 *
 * Injector:
 *   GeneralPolicy:
 *     class: CustomHeaderPolicy
 *     properties:
 *       headers:
 *         Cache-Control: "public, max-age=600, no-transform"
 *         Custom-Header: "Hello"
 * HomePage_Controller:
 *   dependencies:
 *     Policy: '%$GeneralPolicy'
 *   extensions:
 *     - ControllerPolicyApplicator
 */
class CustomHeaderPolicy implements ControllerPolicy {

	/**
	 * @var array
	 */
	public $headers = array();

	/**
	 * @param Object $originator
	 * @param SS_HTTPRequest $request
	 * @param SS_HTTPResponse $response
	 * @param DataModel $model
	 */
	public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		foreach($this->headers as $key => $value) {
			$response->addHeader($key, $value);
		}
	}
}
