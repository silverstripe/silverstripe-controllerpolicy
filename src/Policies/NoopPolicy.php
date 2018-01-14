<?php

namespace SilverStripe\ControllerPolicy\Policies;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ControllerPolicy\ControllerPolicy;

/**
 * This policy can be used to override another policy with no-op.
 */
class NoopPolicy implements ControllerPolicy
{
    /**
     * @param object $originator
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function applyToResponse($originator, HTTPRequest $request, HTTPResponse $response)
    {
        return true;
    }
}
