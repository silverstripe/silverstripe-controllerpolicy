<?php

namespace SilverStripe\ControllerPolicy\Tests;

use Page;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ControllerPolicy\PageControlledPolicy;
use SilverStripe\ControllerPolicy\Policies\CachingPolicy;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\LiteralField;

class PageControlledPolicyTest extends SapphireTest
{
    protected static $fixture_file = 'PageControlledPolicyTest.yml';

    protected static $required_extensions = [
        Page::class => [
            PageControlledPolicy::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        // Load some default caching policy configuration
        Injector::inst()->load([
            'GeneralCachingPolicy' => [
                'class' => CachingPolicy::class,
                'properties' => [
                    'CacheAge' => 900, // 15 mins
                ]
            ]
        ]);

        Config::modify()->set(CachingPolicy::class, 'disable_cache_age_in_dev', false);
    }

    public function testInfoMessageIsShownToAdminUsersOnly()
    {
        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'some_page');

        $this->logInWithPermission('ADMIN');
        $fields = $page->getCMSFields();
        $this->assertInstanceOf(LiteralField::class, $fields->fieldByName('Root.Caching.Instruction'));

        $this->logOut();
        $fields = $page->getCMSFields();
        $this->assertNull($fields->fieldByName('Root.Caching.Instruction'));
    }

    public function testCustomMaxAgeIsHonoured()
    {
        /** @var Page $page */
        $page = $this->objFromFixture(Page::class, 'some_page');
        $controller = ModelAsController::controller_for($page);

        $cachingPolicy = new CachingPolicy();
        $request = new HTTPRequest('GET', '/');
        $response = new HTTPResponse('Hello world');
        $cachingPolicy->applyToResponse($controller, $request, $response);

        $this->assertContains(
            'max-age=1620',
            (string) $response->getHeader('Cache-Control'),
            'CMS defined max age value (27 minutes) is used instead of the default (15 mins)'
        );
    }
}
