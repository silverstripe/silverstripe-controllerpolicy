<?php
/**
 * This policy can be used to override another policy with no-op.
 */
class NoopPolicy implements ControllerPolicy
{
    /**
     * @param Object $originator
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param DataModel $model
     */
    public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        // no op
    }
}
