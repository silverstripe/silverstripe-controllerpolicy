<?php

namespace SilverStripe\ControllerPolicy\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ControllerPolicy\Policies\CustomHeaderPolicy;
use SilverStripe\Dev\SapphireTest;

class CustomHeaderPolicyTest extends SapphireTest
{
    /**
     * @var HTTPRequest
     */
    protected $request;

    /**
     * @var HTTPResponse
     */
    protected $response;

    /**
     * @var CustomHeaderPolicy
     */
    protected $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CustomHeaderPolicy();
        $this->request = new HTTPRequest('GET', '/');
        $this->response = new HTTPResponse();
    }

    public function testCustomHeadersAreAddedToResponse()
    {
        $this->policy->setHeaders(['X-Vegetable' => 'Banana']);
        $this->policy->addHeader('X-Animal', 'Monkey');
        $this->policy->applyToResponse(null, $this->request, $this->response);

        $this->assertSame('Banana', $this->response->getHeader('X-Vegetable'));
        $this->assertSame('Monkey', $this->response->getHeader('X-Animal'));
    }

    public function testEmptyValuesUnsetHeaders()
    {
        $this->policy->setHeaders([
            'X-Vary' => 'Cookie, X-Forwarded-Protocol',
            'X-Animal' => 'Monkey',
        ]);
        $this->policy->applyToResponse(null, $this->request, $this->response);

        $this->policy->addHeader('X-Animal', '');
        $this->policy->applyToResponse(null, $this->request, $this->response);

        $this->assertSame('Cookie, X-Forwarded-Protocol', $this->response->getHeader('X-Vary'));
        $this->assertEmpty($this->response->getHeader('X-Animal'));
    }
}
