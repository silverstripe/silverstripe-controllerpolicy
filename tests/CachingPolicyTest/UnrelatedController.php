<?php

namespace SilverStripe\ControllerPolicy\Tests\CachingPolicyTest;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

class UnrelatedController extends Controller implements TestOnly
{
    private static $allowed_actions = [
        'test',
    ];

    private static $url_segment = 'UnrelatedController';

    public function test()
    {
        return 'Hello world!';
    }
}
