<?php

declare(strict_types=1);

namespace Shanginn\Jev\Tests\Unit;

use Amp\Cancellation;
use Amp\Http\Client\{DelegateHttpClient, HttpClient, Request, Response};
use PHPUnit\Framework\TestCase;
use Shanginn\Jev\Exception\TransportException;
use Shanginn\Jev\Http\AmpTransport;

final class TransportTest extends TestCase
{
    public function testWireRequestUsesDecisionsEndpointAndAttribution(): void
    {
        $delegate = new class implements DelegateHttpClient {
            public ?Request $captured = null;
            public function request(Request $request, Cancellation $cancellation): Response
            {
                $this->captured = $request;
                return new Response('1.1', 200, null, ['X-Request-ID' => 'req-123'], '{}', $request);
            }
        };
        $transport = new AmpTransport('fixture-not-a-real-key', timeout: 7.0, httpReferer: 'https://example.com', appTitle: 'Example', appCategories: 'sdk', client: new HttpClient($delegate, []));
        $response = $transport->send('{"state":"hello"}');
        $request = $delegate->captured;
        self::assertNotNull($request);
        self::assertSame('https://openrouter.ai/api/alpha/decisions', (string) $request->getUri());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('Bearer fixture-not-a-real-key', $request->getHeader('authorization'));
        self::assertSame('application/json', $request->getHeader('content-type'));
        self::assertSame('https://example.com', $request->getHeader('http-referer'));
        self::assertSame('Example', $request->getHeader('x-openrouter-title'));
        self::assertSame('sdk', $request->getHeader('x-openrouter-categories'));
        self::assertSame(7.0, $request->getTransferTimeout());
        self::assertSame('req-123', $response->header('x-request-id'));
        self::assertSame('{}', $response->body);
        ob_start();
        var_dump($transport);
        $debug = ob_get_clean();
        self::assertIsString($debug);
        self::assertStringNotContainsString('fixture-not-a-real-key', $debug);
        $this->expectException(\LogicException::class);
        serialize($transport);
    }

    public function testUnderlyingFailureCannotLeakCredential(): void
    {
        $delegate = new class implements DelegateHttpClient {
            public function request(Request $request, Cancellation $cancellation): Response
            {
                throw new \RuntimeException('Echoed ' . $request->getHeader('authorization'));
            }
        };
        $transport = new AmpTransport('fixture-secret', client: new HttpClient($delegate, []));
        try {
            $transport->send('{}');
            self::fail();
        } catch (TransportException $exception) {
            self::assertStringNotContainsString('fixture-secret', (string) $exception);
            self::assertNull($exception->getPrevious());
        }
    }
}
