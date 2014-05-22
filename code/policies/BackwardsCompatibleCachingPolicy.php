<?php
/**
 * Caching policy that replicates what SilverStripe Framework currently does in HTTP::set_cache_headers
 * as close as possible, but that is slightly more configurable via cacheAge and vary config options.
 * Can be used to replicate default SS behaviour without being forced to use the global HTTP::set_cache_age.
 *
 * Still hooks into globals provided by HTTP to get the last modification time and etags.
 *
 * TODO, requires core changes:
 * - remove reliance on self and inheritance on HTTP.
 * - remove HTTP::set_cache_headers function and call from core.
 * - remove reliance on other globals like $_COOKIE
 *
 * Example usage:
 *
 * Injector:
 *   GeneralCachingPolicy:
 *     class: BackwardsCompatibleCachingPolicy
 *     properties:
 *       cacheAge: 300
 *       vary: 'Cookie, X-Forwarded-Protocol, Accept'
 * Controller:
 *   dependencies:
 *     Policies: '%$GeneralCachingPolicy'
 */

class BackwardsCompatibleCachingPolicy extends HTTP implements ControllerPolicy {

	/**
	 * @var int $cacheAge Max-age seconds to cache for.
	 */
	public $cacheAge = 0;

	/**
	 * @var string $vary Vary string to add. Do not add user-agent unless you vary on it and you have configured
	 *	user-agent clustering in some way, otherwise this will be an equivalent to disabling caching as there
	 *	is a lot of different UAs in the wild.
	 */
	public $vary = 'Cookie, X-Forwarded-Protocol, User-Agent, Accept';

	/**
	 * Copied and adjusted from HTTP::add_cache_headers
	 */
	public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {
		$cacheAge = $this->cacheAge;

		// Development sites have frequently changing templates; this can get stuffed up by the code
		// below.
		if(Director::isDev()) $cacheAge = 0;

		// Popuplate $responseHeaders with all the headers that we want to build
		$responseHeaders = array();
		if(function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			if(isset($requestHeaders['X-Requested-With']) && $requestHeaders['X-Requested-With']=='XMLHttpRequest') {
				$cacheAge = 0;
			}
			// bdc: now we must check for DUMB IE6:
			if(isset($requestHeaders['x-requested-with']) && $requestHeaders['x-requested-with']=='XMLHttpRequest') {
				$cacheAge = 0;
			}
		}

		if($cacheAge > 0) {
			$responseHeaders["Cache-Control"] = "max-age=" . $cacheAge . ", must-revalidate, no-transform";
			$responseHeaders["Pragma"] = "";
			$responseHeaders['Vary'] = $this->vary;
		}
		else {
			if($response) {
				// Grab header for checking. Unfortunately HTTPRequest uses a mistyped variant.
				$contentDisposition = $response->getHeader('Content-disposition');
				if (!$contentDisposition) $contentDisposition = $response->getHeader('Content-Disposition');
			}

			if(
				$response &&
				Director::is_https() &&
				strstr($_SERVER["HTTP_USER_AGENT"], 'MSIE')==true &&
				strstr($contentDisposition, 'attachment;')==true
			) {
				// IE6-IE8 have problems saving files when https and no-cache are used
				// (http://support.microsoft.com/kb/323308)
				// Note: this is also fixable by ticking "Do not save encrypted pages to disk" in advanced options.
				$responseHeaders["Cache-Control"] = "max-age=3, must-revalidate, no-transform";
				$responseHeaders["Pragma"] = "";
			} else {
				$responseHeaders["Cache-Control"] = "no-cache, max-age=0, must-revalidate, no-transform";
			}
		}

		if(self::$modification_date && $cacheAge > 0) {
			$responseHeaders["Last-Modified"] = self::gmt_date(self::$modification_date);

			// Chrome ignores Varies when redirecting back (http://code.google.com/p/chromium/issues/detail?id=79758)
			// which means that if you log out, you get redirected back to a page which Chrome then checks against 
			// last-modified (which passes, getting a 304)
			// when it shouldn't be trying to use that page at all because it's the "logged in" version.
			// By also using and etag that includes both the modification date and all the varies 
			// values which we also check against we can catch this and not return a 304
			$etagParts = array(self::$modification_date, serialize($_COOKIE));
			$etagParts[] = Director::is_https() ? 'https' : 'http';
			if (isset($_SERVER['HTTP_USER_AGENT'])) $etagParts[] = $_SERVER['HTTP_USER_AGENT'];
			if (isset($_SERVER['HTTP_ACCEPT'])) $etagParts[] = $_SERVER['HTTP_ACCEPT'];

			$etag = sha1(implode(':', $etagParts));
			$responseHeaders["ETag"] = $etag;

			// 304 response detection
			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				$ifModifiedSince = strtotime(stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']));

				// As above, only 304 if the last request had all the same varies values
				// (or the etag isn't passed as part of the request - but with chrome it always is)
				$matchesEtag = !isset($_SERVER['HTTP_IF_NONE_MATCH']) || $_SERVER['HTTP_IF_NONE_MATCH'] == $etag;

				if($ifModifiedSince >= self::$modification_date && $matchesEtag) {
					if($response) {
						$response->setStatusCode(304);
						$response->setBody('');
					} else {
						header('HTTP/1.0 304 Not Modified');
						die();
					}
				}
			}

			$expires = time() + $cacheAge;
			$responseHeaders["Expires"] = self::gmt_date($expires);
		}

		if(self::$etag) {
			$responseHeaders['ETag'] = self::$etag;
		}
		
		// Now that we've generated them, either output them or attach them to the SS_HTTPResponse as appropriate
		foreach($responseHeaders as $k => $v) {
			$response->addHeader($k, $v);
		}
	}

}

