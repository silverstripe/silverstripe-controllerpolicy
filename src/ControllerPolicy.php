<?php
/**
 * Interface for per-controller policies.
 */
interface ControllerPolicy
{
    /**
     * @param Object $originator
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param DataModel $model
     */
    public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model);
}
