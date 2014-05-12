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
 * HomePage_Controller:
 *   dependencies:
 *     Policies: '%$CachingPolicy'
 */
class CachingPolicy implements ControllerPolicy {

	/**
	 * @var int $cacheAge Max-age seconds to cache for.
	 */
	public $cacheAge = 0;

	/**
	 * @var string $vary Vary string to add. Do not add user-agent unless you vary on it and you have configured
	 *	user-agent clustering in some way, otherwise this will be an equivalent to disabling caching as there
	 *	is a lot of different UAs in the wild.
	 */
	public $vary = 'Cookie, X-Forwarded-Protocol, Accept';

	public function applyToResponse(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		$response->addHeader('Cache-Control', "max-age=" . $this->cacheAge . ", must-revalidate, no-transform");
		$response->addHeader('Pragma', '');
		$response->addHeader('Vary', $this->vary);
	}

}

