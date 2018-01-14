<?php

namespace SilverStripe\ControllerPolicy\Tests\CachingPolicyTest;

use SilverStripe\Control\Controller;
use SilverStripe\ControllerPolicy\Policies\CachingPolicy;
use SilverStripe\Dev\TestOnly;

class CachingPolicyController extends Controller implements TestOnly
{
    private static $dependencies = [
        'Policies' => '%$' . CachingPolicy::class,
    ];

    private static $allowed_actions = [
        'test',
    ];

    public function test()
    {
        return 'Hello world!';
    }
}
