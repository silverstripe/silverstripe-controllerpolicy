<?php

namespace SilverStripe\ControllerPolicy\Tests;

use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestProcessor;
use SilverStripe\ControllerPolicy\ControllerPolicyRequestFilter;
use SilverStripe\ControllerPolicy\Policies\CachingPolicy;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CallbackCachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\UnrelatedController;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;

class CachingPolicyTest extends FunctionalTest
{
    private $configCachingPolicy = [
        CachingPolicy::class => [
            'class' => CachingPolicy::class,
            'properties' => [
                'cacheAge' => '999',
                'vary' => 'X-EyeColour',
            ],
        ],
    ];

    protected static $extra_controllers = [
        CachingPolicyController::class,
        CallbackCachingPolicyController::class,
        UnrelatedController::class,
    ];

    public function makeRequest($config, $controller, $url)
    {
        Injector::inst()->load($config);

        // Just doing the following in the test results in two disparate ControllerPolicyRequestFilters: one for the
        // controller, and another for the RequestProcessor - even though it should be injected as a singleton. This
        // prevents us from actually testing anything because our policy is wiped out (am I missing something?).
        // $response = $this->get('CachingPolicy_Controller/test');

        // Instead construct the request pipeline manually. It's ugly, but it works.
        $filter = Injector::inst()->get(ControllerPolicyRequestFilter::class);
        $processor = Injector::inst()->get(RequestProcessor::class);
        $processor->setFilters([$filter]);

        // Excercise the controller.
        $controller = Injector::inst()->create($controller);
        $controller->setRequestFilter($filter);
        $controller->doInit();
//        $controller->extend('onAfterInit');
        $request = new HTTPRequest('GET', 'CachingPolicyController/test');
        $response = $processor->process($request, function (HTTPRequest $request) {
            return new HTTPResponse();
        });

        return $response;
    }

    public function testConfigured()
    {
        $response = $this->makeRequest(
            $this->configCachingPolicy,
            CachingPolicyController::class,
            'CachingPolicyController/test'
        );

        $this->assertEquals(
            'max-age=999, must-revalidate, no-transform',
            $response->getHeader('Cache-Control'),
            'Header appears as configured'
        );
        $this->assertEquals(
            'X-EyeColour',
            $response->getHeader('Vary'),
            'Header appears as configured'
        );
    }

    public function testCallbackOverride()
    {
        $response = $this->makeRequest(
            $this->configCachingPolicy,
            CallbackCachingPolicyController::class,
            'CallbackCachingPolicy_Controller/test'
        );

        $this->assertEquals(
            $response->getHeader('Cache-Control'),
            'max-age=1001, must-revalidate, no-transform',
            'Controller\'s getCacheAge() overrides the configuration'
        );
        $this->assertEquals(
            $response->getHeader('Vary'),
            'X-HeightWeight',
            'Controller\'s getVary() overrides the configuration'
        );
        $this->assertEquals(
            $response->getHeader('Last-Modified'),
            HTTP::gmt_date('5000'),
            'Controller\'s getModificationTimestamp overrides the HTTP::$modification_date'
        );
    }

    public function testUnrelated()
    {
        $response = $this->makeRequest(
            $this->configCachingPolicy,
            UnrelatedController::class,
            'Unrelated_Controller/test'
        );

        $this->assertNull($response->getHeader('Cache-Control'), 'Headers on unrelated controller are unaffected');
        $this->assertNull($response->getHeader('Vary'), 'Headers on unrelated controller are unaffected');
    }

    public function testModificationDateFromDataObjects()
    {
        // Trigger updates to HTTP::$modification_date.
        new DataObject(['LastEdited' => '1970-01-01 00:02']);
        new DataObject(['LastEdited' => '1970-01-01 00:01']);
        new DataObject(['LastEdited' => '1970-01-01 00:03']);

        $response = $this->makeRequest(
            $this->configCachingPolicy,
            CachingPolicyController::class,
            'CachingPolicyController/test'
        );

        $this->assertEquals(
            $response->getHeader('Last-Modified'),
            HTTP::gmt_date(strtotime('1970-01-01 00:03')),
            'Most recent LastEdited value prevails over the older ones'
        );
    }
}
