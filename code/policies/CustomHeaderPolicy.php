<?php
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
     * @param Object $originator
     * @param SS_HTTPRequest $request
     * @param SS_HTTPResponse $response
     * @param DataModel $model
     */
    public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
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
