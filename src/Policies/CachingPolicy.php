<?php

namespace SilverStripe\ControllerPolicy\Policies;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\ControllerPolicy\ControllerPolicy;

/**
 * A rewrite of the default SilverStripe behaviour allowing more customisation. Consuming code can provide its own
 * callbacks for providing custom cacheAge, vary and timestamp parameters.
 *
 * See PageControlledPolicy as an example implementation of such customisation that applies on top of default.
 *
 * Extends HTTP to get access to globals describing the Last-Modified and Etag data.
 */

class CachingPolicy extends HTTP implements ControllerPolicy
{
    /**
     * Whether to disable the cache age (set to zero) in dev environments
     *
     * @config
     * @var bool
     */
    private static $disable_cache_age_in_dev = true;

    /**
     * Max-age seconds to cache for if configuration not available from the originator.
     *
     * @var int
     */
    protected $cacheAge = 0;

    /**
     * Vary string to add if configuration is not available from the originator.
     *
     * Note on vary headers: Do not add user-agent unless you vary on it AND you have configured user-agent
     * clustering in some way, otherwise this will be an equivalent to disabling caching as there
     * is a lot of different UAs in the wild.
     *
     * @var string
     */
    protected $vary = '';

    /**
     * Set the cache age
     *
     * @param int $cacheAge
     * @return $this
     */
    public function setCacheAge($cacheAge)
    {
        $this->cacheAge = $cacheAge;
        return $this;
    }

    /**
     * Get the cache age
     *
     * @return int
     */
    public function getCacheAge()
    {
        return $this->cacheAge;
    }

    /**
     * Set the "vary" content header
     *
     * @param string $vary
     * @return $this
     */
    public function setVary($vary)
    {
        $this->vary = $vary;
        return $this;
    }

    /**
     * Get the "vary" content header
     *
     * @return string
     */
    public function getVary()
    {
        return $this->vary;
    }

    /**
     * @see HTTP::add_cache_headers()
     *
     * @param object $originator
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function applyToResponse($originator, HTTPRequest $request, HTTPResponse $response)
    {
        $cacheAge = $this->getCacheAge();
        $vary = $this->getVary();

        // Allow overriding max-age from the object hooked up to the policed controller.
        if ($originator->hasMethod('getCacheAge')) {
            $extendedCacheAge = $originator->getCacheAge($cacheAge);
            if ($extendedCacheAge !== null) {
                $cacheAge = $extendedCacheAge;
            }
        }

        // Development sites have frequently changing templates; this can get stuffed up by the code
        // below.
        if (Director::isDev() && $this->config()->get('disable_cache_age_in_dev')) {
            $cacheAge = 0;
        }

        // Same for vary, but probably less useful.
        if ($originator->hasMethod('getVary')) {
            $extendedVary = $originator->getVary($vary);
            if ($extendedVary !== null) {
                $vary = $extendedVary;
            }
        }

        // Enable caching via core APIs
        HTTPCacheControlMiddleware::singleton()->enableCache();

        if ($cacheAge > 0) {
            HTTPCacheControlMiddleware::singleton()->setMaxAge($cacheAge);
        }

        // Merge vary into response
        if ($vary) {
            HTTPCacheControlMiddleware::singleton()->addVary($vary);
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

        $expires = time() + HTTPCacheControlMiddleware::singleton()->getDirective('max-age');
        $response->addHeader("Expires", self::gmt_date($expires));

        // Now that we've generated them, either output them or attach them to the SS_HTTPResponse as appropriate
        HTTPCacheControlMiddleware::singleton()->applyToResponse($response);
    }
}
