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

        // Merge custom vary into response
        if ($this->vary) {
            $vary = self::combineVary($this->vary, $response->getHeader('Vary'));
            $response->addHeader('Vary', $vary);
        }

        // Ensure we override any existing cache policy
        $response->removeHeader('Cache-Control');

        static::add_cache_headers($response);
    }
}
