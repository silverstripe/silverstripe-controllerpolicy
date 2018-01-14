<?php

namespace SilverStripe\ControllerPolicy\Policies;

use DataModel;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ControllerPolicy\ControllerPolicy;

/**
 * This policy can be used to write or delete arbitrary headers. Set the header to empty string ("")
 * to suppress that header.
 *
 * Configuration:
 *
 * Injector:
 *   GeneralPolicy:
 *     class: CustomHeaderPolicy
 *     properties:
 *       headers:
 *         Cache-Control: "public, max-age=600, no-transform"
 *         Custom-Header: "Hello"
 *         Vary: ""
 * HomePage_Controller:
 *   dependencies:
 *     Policies:
 *       - '%$GeneralPolicy'
 *   extensions:
 *     - ControllerPolicyApplicator
 */
class CustomHeaderPolicy implements ControllerPolicy
{

    /**
     * @var array
     */
    public $headers = array();

    /**
     * @param object $originator
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function applyToResponse($originator, HTTPRequest $request, HTTPResponse $response)
    {
        foreach ($this->headers as $key => $value) {
            if ($value!=="") {
                $response->addHeader($key, $value);
            } else {
                $response->removeHeader($key);
            }
        }
    }
}
