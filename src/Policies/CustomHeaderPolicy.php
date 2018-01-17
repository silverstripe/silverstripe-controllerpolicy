<?php

namespace SilverStripe\ControllerPolicy\Policies;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ControllerPolicy\ControllerPolicy;

/**
 * This policy can be used to write or delete arbitrary headers. Set the header to empty string ("")
 * to suppress that header.
 *
 * Configuration:
 *
 * SilverStripe\Core\Injector\Injector:
 *   GeneralPolicy:
 *     class: YourVendor\YourModule\CustomHeaderPolicy
 *     properties:
 *       headers:
 *         Cache-Control: "public, max-age=600, no-transform"
 *         Custom-Header: "Hello"
 *         Vary: ""
 * HomePageController:
 *   dependencies:
 *     Policies:
 *       - '%$GeneralPolicy'
 *   extensions:
 *     - SilverStripe\ControllerPolicy\ControllerPolicyApplicator
 */
class CustomHeaderPolicy implements ControllerPolicy
{
    /**
     * @var array
     */
    protected $headers = [];

    /**
     * Set the full array of headers to apply to the response
     *
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Get the list of headers to apply to the response
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Add a specific header
     *
     * @param string $key
     * @param string|int $value
     * @return $this
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @param object $originator
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function applyToResponse($originator, HTTPRequest $request, HTTPResponse $response)
    {
        foreach ($this->getHeaders() as $key => $value) {
            if ($value !== "") {
                $response->addHeader($key, $value);
            } else {
                $response->removeHeader($key);
            }
        }
    }
}
