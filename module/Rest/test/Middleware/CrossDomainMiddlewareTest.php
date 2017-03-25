<?php
namespace ShlinkioTest\Shlink\Rest\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Shlinkio\Shlink\Rest\Middleware\CrossDomainMiddleware;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class CrossDomainMiddlewareTest extends TestCase
{
    /**
     * @var CrossDomainMiddleware
     */
    protected $middleware;
    /**
     * @var ObjectProphecy
     */
    protected $delegate;

    public function setUp()
    {
        $this->middleware = new CrossDomainMiddleware();
        $this->delegate = $this->prophesize(DelegateInterface::class);
    }

    /**
     * @test
     */
    public function nonCrossDomainRequestsAreNotAffected()
    {
        $originalResponse = new Response();
        $this->delegate->process(Argument::any())->willReturn($originalResponse)->shouldbeCalledTimes(1);

        $response = $this->middleware->process(ServerRequestFactory::fromGlobals(), $this->delegate->reveal());
        $this->assertSame($originalResponse, $response);

        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers', $headers);
    }

    /**
     * @test
     */
    public function anyRequestIncludesTheAllowAccessHeader()
    {
        $originalResponse = new Response();
        $this->delegate->process(Argument::any())->willReturn($originalResponse)->shouldbeCalledTimes(1);

        $response = $this->middleware->process(
            ServerRequestFactory::fromGlobals()->withHeader('Origin', 'local'),
            $this->delegate->reveal()
        );
        $this->assertNotSame($originalResponse, $response);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Headers', $headers);
    }

    /**
     * @test
     */
    public function optionsRequestIncludesMoreHeaders()
    {
        $originalResponse = new Response();
        $request = ServerRequestFactory::fromGlobals()->withMethod('OPTIONS')->withHeader('Origin', 'local');
        $this->delegate->process(Argument::any())->willReturn($originalResponse)->shouldbeCalledTimes(1);

        $response = $this->middleware->process($request, $this->delegate->reveal());
        $this->assertNotSame($originalResponse, $response);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
    }
}
