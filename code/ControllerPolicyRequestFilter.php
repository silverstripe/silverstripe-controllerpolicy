<?php
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
        if ($ignoreRegexes = Config::inst()->get('ControllerPolicyRequestFilter', 'ignoreDomainRegexes')) {
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

    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model)
    {
        // No-op, we don't know the controller at this stage.
        return true;
    }

    /**
     * Apply all the requested policies.
     *
     * @param  SS_HTTPRequest  $request
     * @param  SS_HTTPResponse $response
     * @param  DataModel       $model
     * @return boolean
     */
    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        // Ignore by regexes.
        if ($this->isIgnoredDomain($_SERVER['HTTP_HOST'])) {
            return true;
        }

        foreach ($this->requestedPolicies as $requestedPolicy) {
            $requestedPolicy['policy']->applyToResponse(
                $requestedPolicy['originator'],
                $request,
                $response,
                $model
            );
        }

        return true;
    }
}
