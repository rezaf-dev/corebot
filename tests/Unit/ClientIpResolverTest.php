<?php

use App\Services\ClientIpResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

it('uses the configured proxy header when proxy mode is enabled', function () {
    config([
        'client_ip.mode' => 'proxy',
        'client_ip.header' => 'CF-Connecting-IP',
        'client_ip.log' => false,
    ]);

    $request = Request::create('/api/public/chat/start', 'POST', server: [
        'REMOTE_ADDR' => '2.29.36.220',
        'HTTP_CF_CONNECTING_IP' => '203.0.113.25',
    ]);

    expect(app(ClientIpResolver::class)->resolve($request))->toBe('203.0.113.25');
});

it('falls back to the Laravel resolved IP when proxy headers are invalid', function () {
    config([
        'client_ip.mode' => 'proxy',
        'client_ip.header' => 'CF-Connecting-IP',
        'client_ip.log' => false,
    ]);

    $request = Request::create('/api/public/chat/start', 'POST', server: [
        'REMOTE_ADDR' => '2.29.36.220',
        'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
        'HTTP_X_FORWARDED_FOR' => 'also-not-an-ip',
    ]);

    expect(app(ClientIpResolver::class)->resolve($request))->toBe('2.29.36.220');
});

it('logs the selected IP source and forwarded header values when enabled', function () {
    Log::spy();

    config([
        'client_ip.mode' => 'proxy',
        'client_ip.header' => 'CF-Connecting-IP',
        'client_ip.log' => true,
    ]);

    $request = Request::create('/api/public/chat/start', 'POST', server: [
        'REMOTE_ADDR' => '2.29.36.220',
        'HTTP_CF_CONNECTING_IP' => '203.0.113.25',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.25, 2.29.36.220',
    ]);

    expect(app(ClientIpResolver::class)->resolve($request))->toBe('203.0.113.25');

    Log::shouldHaveReceived('info')->once()->with('Client IP resolved.', Mockery::on(
        fn (array $context): bool => $context['source'] === 'CF-Connecting-IP'
            && $context['resolved_ip'] === '203.0.113.25'
            && $context['remote_addr'] === '2.29.36.220'
            && $context['x_forwarded_for'] === '203.0.113.25, 2.29.36.220',
    ));
});
