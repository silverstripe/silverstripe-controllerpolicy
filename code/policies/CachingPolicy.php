<?php
/**
 * A rewrite of the default SilverStripe behaviour allowing more customisation. Consuming code can provide its own
 * callbacks for providing custom cacheAge, vary and timestamp parameters.
 *
 * See PageControlledPolicy as an example implementation of such customisation that applies on top of default.
 */

class CachingPolicy extends HTTP implements ControllerPolicy
{
    /**
     * Extends HTTP to get access to globals describing the Last-Modified and Etag data.
     */

    /**
     * @var int $cacheAge Max-age seconds to cache for if configuration not available from the originator.
     */
    public $cacheAge = 0;

    /**
     * @var string $vary Vary string to add if configuration is not available from the originator.
     *		Note on vary headers: Do not add user-agent unless you vary on it AND you have configured user-agent
     *		clustering in some way, otherwise this will be an equivalent to disabling caching as there
     *		is a lot of different UAs in the wild.
     */
    public $vary = 'Cookie, X-Forwarded-Protocol';

    /**
     * @param Object $originator
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param DataModel $model
     */
    public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        $cacheAge = $this->cacheAge;
        $vary = $this->vary;

                // Allow overriding max-age from the object hooked up to the policed controller.
        if ($originator->hasMethod('getCacheAge')) {
            /** @var PageControlledPolicy $originator */
            $extendedCacheAge = $originator->getCacheAge($cacheAge);
            if ($extendedCacheAge !== null) {
                $cacheAge = $extendedCacheAge;
            }
        }

        // Same for vary, but probably less useful.
        if ($originator->hasMethod('getVary')) {
            $extendedVary = $originator->getVary($vary);
            if ($extendedVary!==null) {
                $vary = $extendedVary;
            }
        }

        // Enable caching via core APIs
        HTTPCacheControl::singleton()->enableCache();

        if ($cacheAge > 0) {
            HTTPCacheControl::singleton()->setMaxAge($cacheAge);
        }

        // Merge vary into response
        if ($vary) {
            $vary = self::combineVary($vary, $response->getHeader('Vary'));
            $response->addHeader('Vary', $vary);
        }

        // Find out when the URI was last modified. Allows customisation, but fall back HTTP timestamp collector.
        if ($originator->hasMethod('getModificationTimestamp')) {
            $timestamp = $originator->getModificationTimestamp();
        } else {
            $timestamp = HTTP::$modification_date;
        }

        if ($timestamp) {
            $response->addHeader("Last-Modified", self::gmt_date($timestamp));
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
                $response->addHeader('ETag', $etag);

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
        $response->addHeader("Expires", self::gmt_date($expires));

        // Now that we've generated them, either output them or attach them to the SS_HTTPResponse as appropriate
        HTTPCacheControl::singleton()->applyToResponse($response);
    }
}
