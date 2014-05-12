<?php
/**
 * Caching policy that demonstrates the ability to change the headers of a request attached to specific Controller.
 *
 * Example usage:
 *
 * Injector:
 *   CachingPolicy:
 *     class: CachingPolicy
 *     properties:
 *       cacheAge: 300
 *
 * HomePage_Controller:
 *   extensions:
 *     - ControllerPolicyApplicator('CachingPolicy')
 */

class CachingPolicy implements ControllerPolicy {

	public $cacheAge = 0;

	public function applyToResponse(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		$response->addHeader('Cache-Control', "max-age=" . $this->cacheAge . ", must-revalidate, no-transform");
		$response->addHeader('Pragma', '');
		$response->addHeader('Vary', 'Cookie, X-Forwarded-Protocol, Accept');
	}

}

