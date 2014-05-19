<?php
/**
 * This policy can be used to override another policy with no-op.
 */
class NoopPolicy implements ControllerPolicy {

	public function applyToResponse($originator, SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model) {

		return true;

	}

}

