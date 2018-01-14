<?php

namespace SilverStripe\ControllerPolicy;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;

/**
 * This request filter accepts registrations of policies to be applied at the end of the control pipeline.
 * The policies will be applied in the order they are added, and will override HTTP::add_cache_headers.
 */
class ControllerPolicyRequestFilter implements RequestFilter
{
    /**
     * @var array $ignoreDomainRegexes Force some domains to be ignored. Accepts one wildcard at the beginning.
     */
    private static $ignoreDomainRegexes = array();

    /**
     * An associative array containing the 'originator' and 'policy' reference.
     *
     * @var array
     */
    private $requestedPolicies = array();

    /**
     * Check if the given domain is on the list of ignored domains.
     *
     * @param string $domain
     * @return boolean
     */
    public function isIgnoredDomain($domain)
    {
        if ($ignoreRegexes = Config::inst()->get(ControllerPolicyRequestFilter::class, 'ignoreDomainRegexes')) {
            foreach ($ignoreRegexes as $ignore) {
                if (preg_match($ignore, $domain)>0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a policy tuple.
     */
    public function requestPolicy($originator, $policy)
    {
        $this->requestedPolicies[] = array('originator' => $originator, 'policy' => $policy);
    }

    public function clearPolicies()
    {
        $this->requestedPolicies = array();
    }

    public function preRequest(HTTPRequest $request, Session $session)
    {
        // No-op, we don't know the controller at this stage.
        return true;
    }

    /**
     * Apply all the requested policies.
     *
     * @param  HTTPRequest  $request
     * @param  HTTPResponse $response
     * @return boolean
     */
    public function postRequest(HTTPRequest $request, HTTPResponse $response)
    {
        if (!Director::is_cli() && isset($_SERVER['HTTP_HOST'])) {
            // Ignore by regexes.
            if ($this->isIgnoredDomain($_SERVER['HTTP_HOST'])) {
                return true;
            }
        }

        foreach ($this->requestedPolicies as $requestedPolicy) {
            $requestedPolicy['policy']->applyToResponse(
                $requestedPolicy['originator'],
                $request,
                $response
            );
        }

        return true;
    }
}
