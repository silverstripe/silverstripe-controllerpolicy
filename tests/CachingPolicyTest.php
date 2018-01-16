<?php

namespace SilverStripe\ControllerPolicy\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\ControllerPolicy\ControllerPolicyMiddleware;
use SilverStripe\ControllerPolicy\Policies\CachingPolicy;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CallbackCachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\UnrelatedController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;

class CachingPolicyTest extends FunctionalTest
{
    protected static $extra_controllers = [
        CachingPolicyController::class,
        CallbackCachingPolicyController::class,
        UnrelatedController::class,
    ];

    private $configCachingPolicy = [
        CachingPolicy::class => [
            'class' => CachingPolicy::class,
            'properties' => [
                'cacheAge' => '999',
                'vary' => 'X-EyeColour',
            ],
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        Config::modify()->set(CachingPolicy::class, 'disable_cache_age_in_dev', false);
    }

    /**
     * Remove any policies from the middleware, since it's assigned to the Director singleton and shared between
     * tests.
     */
    protected function tearDown()
    {
        foreach (Director::singleton()->getMiddlewares() as $middleware) {
            if ($middleware instanceof ControllerPolicyMiddleware) {
                $middleware->clearPolicies();
            }
        }

        parent::tearDown();
    }

    public function makeRequest($config, $controller, $url)
    {
        Injector::inst()->load($config);

        $middleware = Injector::inst()->create(ControllerPolicyMiddleware::class);

        // Exercise the controller.
        $controller = Injector::inst()->create($controller);
        $controller->setMiddleware($middleware);
        $controller->doInit();

        $request = new HTTPRequest('GET', $controller->Link('test'));
        $request->setSession(new Session([]));
        $response = Director::singleton()->handleRequest($request);

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
            'CallbackCachingPolicyController/test'
        );

        $this->assertEquals(
            'max-age=1001, must-revalidate, no-transform',
            $response->getHeader('Cache-Control'),
            'Controller\'s getCacheAge() overrides the configuration'
        );
        $this->assertEquals(
            'X-HeightWeight',
            $response->getHeader('Vary'),
            'Controller\'s getVary() overrides the configuration'
        );
        $this->assertEquals(
            HTTP::gmt_date('5000'),
            $response->getHeader('Last-Modified'),
            'Controller\'s getModificationTimestamp overrides the HTTP::$modification_date'
        );
    }

    public function testUnrelated()
    {
        $response = $this->makeRequest(
            $this->configCachingPolicy,
            UnrelatedController::class,
            'UnrelatedController/test'
        );

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
            HTTP::gmt_date(strtotime('1970-01-01 00:03')),
            $response->getHeader('Last-Modified'),
            'Most recent LastEdited value prevails over the older ones'
        );
    }
}
