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

class BackwardsCompatibleCachingPolicy extends HTTP implements ControllerPolicy
{

    /**
     * @var int $cacheAge Max-age seconds to cache for.
     */
    public $cacheAge = 0;

    /**
     * @var string $vary Vary string to add. Do not add user-agent unless you vary on it and you have configured
     *	user-agent clustering in some way, otherwise this will be an equivalent to disabling caching as there
     *	is a lot of different UAs in the wild.
     */
    public $vary = 'X-Requested-With';

    /**
     * Copied and adjusted from HTTP::add_cache_headers
     *
     * @param Object $originator
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param DataModel $model
     */
    public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        if ($this->cacheAge > 0) {
            HTTPCacheControl::singleton()->setMaxAge($this->cacheAge);
        }

        $config = Config::inst()->forClass(__CLASS__);

        // Development sites have frequently changing templates; this can get stuffed up by the code
        // below.
        if ($config->get('disable_http_cache')) {
            HTTPCacheControl::singleton()->disableCaching();
        }

        // Populate $responseHeaders with all the headers that we want to build
        $responseHeaders = array();
        $cacheControlHeaders = $config->get('cache_control');
        if (!$config->get('cache_ajax_requests') && function_exists('apache_request_headers')) {
            $requestHeaders = array_change_key_case(apache_request_headers(), CASE_LOWER);

            if (array_key_exists('x-requested-with', $requestHeaders) && strtolower($requestHeaders['x-requested-with']) == 'xmlhttprequest') {
                HTTPCacheControl::singleton()->disableCaching();
            }
        }

        $vary = $config->get('vary');
        if ($vary && strlen($vary)) {
            // split the current vary header into it's parts and merge it with the config settings
            // to create a list of unique vary values
            if ($request->getHeader('Vary')) {
                $currentVary = explode(',', $request->getHeader('Vary'));
            } else {
                $currentVary = array();
            }
            $vary = explode(',', $vary);
            $localVary = explode(',', $this->vary);
            $vary = array_merge($currentVary, $vary, $localVary);
            $vary = array_map('trim', $vary);
            $vary = array_unique($vary);
            $vary = implode(', ', $vary);
            $responseHeaders['Vary'] = $vary;
        }

        $contentDisposition = $response->getHeader('Content-Disposition', true);
        if(
            Director::is_https() &&
            isset($_SERVER['HTTP_USER_AGENT']) &&
            strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')==true &&
            strstr($contentDisposition, 'attachment;')==true &&
            (
                HTTPCacheControl::singleton()->hasDirective('no-cache') ||
                HTTPCacheControl::singleton()->hasDirective('no-store')
            )
        ) {
            // IE6-IE8 have problems saving files when https and no-cache/no-store are used
            // (http://support.microsoft.com/kb/323308)
            // Note: this is also fixable by ticking "Do not save encrypted pages to disk" in advanced options.
            HTTPCacheControl::singleton()
                ->privateCache()
                ->removeDirective('no-cache')
                ->removeDirective('no-store');
        }

        if (!empty($cacheControlHeaders)) {
            HTTPCacheControl::singleton()->setDirectivesFromArray($cacheControlHeaders);
        }

        if (self::$modification_date) {
            $responseHeaders["Last-Modified"] = self::gmt_date(self::$modification_date);
        }

        // if we can store the cache responses we should generate and send etags
        if (!HTTPCacheControl::singleton()->hasDirective('no-store')) {

            // Chrome ignores Varies when redirecting back (http://code.google.com/p/chromium/issues/detail?id=79758)
            // which means that if you log out, you get redirected back to a page which Chrome then checks against
            // last-modified (which passes, getting a 304)
            // when it shouldn't be trying to use that page at all because it's the "logged in" version.
            // By also using and etag that includes both the modification date and all the varies
            // values which we also check against we can catch this and not return a 304
            $etag = self::generateETag($response);
            if ($etag) {
                $responseHeaders['ETag'] = $etag;

                // 304 response detection
                if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
                    // As above, only 304 if the last request had all the same varies values
                    // (or the etag isn't passed as part of the request - but with chrome it always is)
                    $matchesEtag = $_SERVER['HTTP_IF_NONE_MATCH'] == $etag;

                    if ($matchesEtag) {
                        $response->setStatusCode(304);
                        $response->setBody('');
                    }
                }
            }
        }

        $expires = time() + HTTPCacheControl::singleton()->getDirective('max-age');
        $responseHeaders["Expires"] = self::gmt_date($expires);

        // Now that we've generated them, either output them or attach them to the SS_HTTPResponse as appropriate
        foreach($responseHeaders as $k => $v) {
            // Set the header now if it's not already set.
            if ($response->getHeader($k) === null) {
                $response->addHeader($k, $v);
            }
        }

        HTTPCacheControl::singleton()->applyToResponse($response);
    }
}
