<?php
/**
 * Interface for per-controller policies.
 */
interface ControllerPolicy {
	
	public function applyToResponse(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model);

}
