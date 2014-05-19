<?php

class CachingPolicyTest extends FunctionalTest {

	function testRequest() {

		Injector::inst()->load(array(
			'CachingPolicy' => array(
				'class' => 'CachingPolicy',
				'properties' => array(
					'cacheAge' => '999',
					'vary' => 'X-EyeColour'
				)
			)
		));

		// The following results in two disparate ControllerPolicyRequestFilters: one for the controller,
		// and another for the RequestProcessor - even though it should be injected as a singleton! This
		// prevents us from actually testing anything because our policy is wiped out (what am I missing?)
		// $response = $this->get('CachingPolicy_Controller/test');

		// Construct the request pipeline manually instead. This is not exactly beautiful.
		$filter = Injector::inst()->get('ControllerPolicyRequestFilter');
		$processor = Injector::inst()->get('RequestProcessor');
		$processor->setFilters(array($filter));

		// Excercise the controller.
		$controller = CachingPolicy_Controller::create();
		$controller->setRequestFilter($filter);
		$controller->extend('onAfterInit');
		$request = new SS_HTTPRequest('GET', 'CachingPolicy_Controller/test');
		$response = new SS_HTTPResponse();
		$processor->postRequest($request, $response, DataModel::inst());

		$this->assertEquals($response->getHeader('Cache-Control'), 'max-age=999, must-revalidate, no-transform');
		$this->assertEquals($response->getHeader('Vary'), 'X-EyeColour');

		$filter->clearPolicies();

		// Excercise the other controller - the one with ability to override.
		$controller = CallbackCachingPolicy_Controller::create();
		$controller->setRequestFilter($filter);
		$controller->extend('onAfterInit');
		$request = new SS_HTTPRequest('GET', 'CallbackCachingPolicy_Controller/test');
		$response = new SS_HTTPResponse();
		$processor->postRequest($request, $response, DataModel::inst());

		$this->assertEquals($response->getHeader('Cache-Control'), 'max-age=1001, must-revalidate, no-transform');
		$this->assertEquals($response->getHeader('Vary'), 'X-HeightWeight');

		$filter->clearPolicies();

		// Excercise the controller without policies.
		$controller = Unrelated_Controller::create();
		$controller->setRequestFilter($filter);
		$controller->extend('onAfterInit');
		$request = new SS_HTTPRequest('GET', 'CallbackCachingPolicy_Controller/test');
		$response = new SS_HTTPResponse();
		$processor->postRequest($request, $response, DataModel::inst());

		$this->assertNull($response->getHeader('Cache-Control'));
		$this->assertNull($response->getHeader('Vary'));

		$filter->clearPolicies();
	}

}

class Unrelated_Controller extends Controller implements TestOnly {

	private static $allowed_actions = array(
		'test'
	);

	public function test() {
		return 'Hello world!';
	}

}

class CachingPolicy_Controller extends Controller implements TestOnly {

	private static $dependencies = array(
		'Policies' => '%$CachingPolicy'
	);

	private static $allowed_actions = array(
		'test'
	);

	public function test() {
		return 'Hello world!';
	}

}

class CallbackCachingPolicy_Controller extends CachingPolicy_Controller {

	public function getCacheAge($age) {
		return '1001';
	}

	public function getVary($vary) {
		return 'X-HeightWeight';
	}

}
