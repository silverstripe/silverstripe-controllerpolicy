<?php

namespace SilverStripe\ControllerPolicy\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Session;
use SilverStripe\ControllerPolicy\ControllerPolicyMiddleware;
use SilverStripe\ControllerPolicy\Policies\CachingPolicy;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\CallbackCachingPolicyController;
use SilverStripe\ControllerPolicy\Tests\CachingPolicyTest\UnrelatedController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

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

    protected function setUp(): void
    {
        parent::setUp();

        Config::modify()->set(CachingPolicy::class, 'disable_cache_age_in_dev', false);

        // Set to disabled at null forcing level, overrides dev mode defaults
        HTTPCacheControlMiddleware::config()
            ->set('defaultForcingLevel', 0);
        HTTPCacheControlMiddleware::reset();
    }

    /**
     * Remove any policies from the middleware, since it's assigned to the Director singleton and shared between
     * tests.
     */
    protected function tearDown(): void
    {
        foreach (Director::singleton()->getMiddlewares() as $middleware) {
            if ($middleware instanceof ControllerPolicyMiddleware) {
                $middleware->clearPolicies();
            }
        }

        parent::tearDown();
    }

    protected function makeRequest($config, $controller, $url)
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

        $directives = $this->getCsvAsArray($response->getHeader('Cache-Control'));

        $this->assertCount(2, $directives);
        $this->assertStringContainsString('max-age=999', $directives);
        $this->assertStringContainsString('must-revalidate', $directives);

        $vary = $this->getCsvAsArray($response->getHeader('Vary'));
        $this->assertArraySubset(
            array_keys(HTTPCacheControlMiddleware::config()->get('defaultVary')),
            $vary,
            'Retains default Vary'
        );
        $this->assertStringContainsString('X-EyeColour', $vary, 'Adds custom vary');
    }

    public function testCallbackOverride()
    {
        $response = $this->makeRequest(
            $this->configCachingPolicy,
            CallbackCachingPolicyController::class,
            'CallbackCachingPolicyController/test'
        );

        $directives = $this->getCsvAsArray($response->getHeader('Cache-Control'));

        $this->assertCount(2, $directives);
        $this->assertStringContainsString('max-age=1001', $directives);
        $this->assertStringContainsString('must-revalidate', $directives);

        $vary = $this->getCsvAsArray($response->getHeader('Vary'));
        $this->assertStringContainsString(
            'X-HeightWeight',
            $vary,
            'Controller\'s getVary() overrides the configuration'
        );
        $this->assertStringNotContainsString('X-EyeColour', $vary);

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

        $defaultVary = array_keys(HTTPCacheControlMiddleware::config()->get('defaultVary'));
        $vary = $this->getCsvAsArray($response->getHeader('Vary'));
        $this->assertEquals($vary, $defaultVary, 'Headers on unrelated controller are unaffected');
    }

    /**
     * @param string $str
     * @return array
     */
    protected function getCsvAsArray($str)
    {
        return array_filter(preg_split("/\s*,\s*/", trim($str)));
    }
}
