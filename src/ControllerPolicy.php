<?php

namespace SilverStripe\ControllerPolicy;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Interface for per-controller policies.
 */
interface ControllerPolicy
{
    /**
     * @param object $originator
     * @param HTTPRequest $request
     * @param HTTPResponse $response
     */
    public function applyToResponse($originator, HTTPRequest $request, HTTPResponse $response);
}
