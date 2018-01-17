<?php

namespace SilverStripe\ControllerPolicy\Policies;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
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
    protected $vary = 'Cookie, X-Forwarded-Protocol';

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

        // Same for vary, but probably less useful.
        if ($originator->hasMethod('getVary')) {
            $extendedVary = $originator->getVary($vary);
            if ($extendedVary !== null) {
                $vary = $extendedVary;
            }
        }

        // Development sites have frequently changing templates; this can get stuffed up by the code
        // below.
        if (Director::isDev() && $this->config()->get('disable_cache_age_in_dev')) {
            $cacheAge = 0;
        }

        // The headers have been sent and we don't have an HTTPResponse object to attach things to; no point in
        // us trying.
        if (headers_sent() && !$response->getBody()) {
            return;
        }

        // Populate $responseHeaders with all the headers that we want to build
        $responseHeaders = [];

        $cacheControlHeaders = HTTP::config()->uninherited('cache_control');

        if ($cacheAge > 0) {
            // Note: must-revalidate means that the cache must revalidate AFTER the entry has gone stale.
            $cacheControlHeaders['must-revalidate'] = 'true';

            $cacheControlHeaders['max-age'] = $cacheAge;

            // Set empty pragma to avoid PHP's session_cache_limiter adding conflicting caching information,
            // defaulting to "nocache" on most PHP configurations (see http://php.net/session_cache_limiter).
            // Since it's a deprecated HTTP 1.0 option, all modern HTTP clients and proxies should
            // prefer the caching information indicated through the "Cache-Control" header.
            $responseHeaders["Pragma"] = "";

            $responseHeaders['Vary'] = $vary;
        }

        foreach ($cacheControlHeaders as $header => $value) {
            if (is_null($value)) {
                unset($cacheControlHeaders[$header]);
            } elseif ((is_bool($value) && $value) || $value === "true") {
                $cacheControlHeaders[$header] = $header;
            } else {
                $cacheControlHeaders[$header] = $header . "=" . $value;
            }
        }

        $responseHeaders['Cache-Control'] = implode(', ', $cacheControlHeaders);
        unset($cacheControlHeaders, $header, $value);

        // Find out when the URI was last modified. Allows customisation, but fall back HTTP timestamp collector.
        if ($originator->hasMethod('getModificationTimestamp')) {
            $timestamp = $originator->getModificationTimestamp();
        } elseif (self::$modification_date && $cacheAge > 0) {
            $timestamp = self::$modification_date;
        }

        if (isset($timestamp)) {
            $responseHeaders["Last-Modified"] = self::gmt_date($timestamp);

            // Chrome ignores Varies when redirecting back (http://code.google.com/p/chromium/issues/detail?id=79758)
            // which means that if you log out, you get redirected back to a page which Chrome then checks against
            // last-modified (which passes, getting a 304)
            // when it shouldn't be trying to use that page at all because it's the "logged in" version.
            // By also using and etag that includes both the modification date and all the varies
            // values which we also check against we can catch this and not return a 304
            $etagParts = array(self::$modification_date, serialize($_COOKIE));
            $etagParts[] = Director::is_https() ? 'https' : 'http';
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $etagParts[] = $_SERVER['HTTP_USER_AGENT'];
            }
            if (isset($_SERVER['HTTP_ACCEPT'])) {
                $etagParts[] = $_SERVER['HTTP_ACCEPT'];
            }

            $etag = sha1(implode(':', $etagParts));
            $responseHeaders["ETag"] = $etag;

            // 304 response detection
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $ifModifiedSince = strtotime(stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']));

                // As above, only 304 if the last request had all the same varies values
                // (or the etag isn't passed as part of the request - but with chrome it always is)
                $matchesEtag = !isset($_SERVER['HTTP_IF_NONE_MATCH']) || $_SERVER['HTTP_IF_NONE_MATCH'] == $etag;

                if ($ifModifiedSince >= self::$modification_date && $matchesEtag) {
                    if ($body) {
                        $body->setStatusCode(304);
                        $body->setBody('');
                    } else {
                        header('HTTP/1.0 304 Not Modified');
                        die();
                    }
                }
            }

            $expires = time() + $cacheAge;
            $responseHeaders["Expires"] = self::gmt_date($expires);
        }

        if (self::$etag) {
            $responseHeaders['ETag'] = self::$etag;
        }

        // etag needs to be a quoted string according to HTTP spec
        if (!empty($responseHeaders['ETag']) && 0 !== strpos($responseHeaders['ETag'], '"')) {
            $responseHeaders['ETag'] = sprintf('"%s"', $responseHeaders['ETag']);
        }

        // Now that we've generated them, either output them or attach them to the HTTPResponse as appropriate
        foreach ($responseHeaders as $k => $v) {
            $response->addHeader($k, $v);
        }
    }
}
