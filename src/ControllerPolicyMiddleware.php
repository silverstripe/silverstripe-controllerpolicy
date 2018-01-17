<?php

namespace SilverStripe\ControllerPolicy;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;

/**
 * This middleware accepts registrations of policies to be applied at the end of the control pipeline.
 * The policies will be applied in the order they are added, and will override HTTP::add_cache_headers.
 */
class ControllerPolicyMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * Force some domains to be ignored. Accepts one wildcard at the beginning.
     *
     * @config
     * @var array
     */
    private static $ignore_domain_regexes = [];

    /**
     * An associative array containing the 'originator' and 'policy' reference.
     *
     * @var array
     */
    protected $requestedPolicies = [];

    /**
     * Check if the given domain is on the list of ignored domains.
     *
     * @param string $domain
     * @return boolean
     */
    public function isIgnoredDomain($domain)
    {
        if ($ignoreRegexes = $this->config()->get('ignore_domain_regexes')) {
            foreach ($ignoreRegexes as $ignore) {
                if (preg_match($ignore, $domain) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a policy tuple.
     *
     * @param Controller $originator
     * @param array $policy
     */
    public function requestPolicy($originator, $policy)
    {
        $this->requestedPolicies[] = ['originator' => $originator, 'policy' => $policy];
    }

    public function clearPolicies()
    {
        $this->requestedPolicies = [];
    }

    /**
     * Apply all the requested policies.
     *
     * @param  HTTPRequest  $request
     * @param  callable $delegate
     * @return HTTPResponse
     */
    public function process(HTTPRequest $request, callable $delegate)
    {
        /** @var HTTPResponse $response */
        $response = $delegate($request);

        // Ignore by regexes.
        if ($this->shouldCheckHttpHost() && $this->isIgnoredDomain($_SERVER['HTTP_HOST'])) {
            return $response;
        }

        foreach ($this->requestedPolicies as $requestedPolicy) {
            /** @var ControllerPolicy $policyInstance */
            $policyInstance = $requestedPolicy['policy'];

            $policyInstance->applyToResponse(
                $requestedPolicy['originator'],
                $request,
                $response
            );
        }

        return $response;
    }

    /**
     * Whether the domain regexes should be checked. Can be partially mocked for unit testing.
     *
     * @return bool
     */
    public function shouldCheckHttpHost()
    {
        return !Director::is_cli() && isset($_SERVER['HTTP_HOST']);
    }
}
