<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientIpResolver
{
    public function resolve(Request $request): ?string
    {
        $mode = config('client_ip.mode', 'request');
        $source = 'request';
        $ipAddress = $request->ip();

        if ($mode === 'proxy') {
            $configuredHeader = (string) config('client_ip.header', 'CF-Connecting-IP');
            $forwardedIp = $this->firstValidIp($request->header($configuredHeader));

            if ($forwardedIp !== null) {
                $ipAddress = $forwardedIp;
                $source = $configuredHeader;
            } else {
                $realIp = $this->firstValidIp($request->header('X-Real-IP'));
                $forwardedForIp = $this->firstValidIp($request->header('X-Forwarded-For'));

                if ($realIp !== null) {
                    $ipAddress = $realIp;
                    $source = 'X-Real-IP';
                } elseif ($forwardedForIp !== null) {
                    $ipAddress = $forwardedForIp;
                    $source = 'X-Forwarded-For';
                }
            }
        }

        if (config('client_ip.log', false)) {
            $configuredHeader = (string) config('client_ip.header', 'CF-Connecting-IP');

            Log::info('Client IP resolved.', [
                'mode' => $mode,
                'source' => $source,
                'resolved_ip' => $ipAddress,
                'remote_addr' => $request->server('REMOTE_ADDR'),
                'configured_header' => $configuredHeader,
                'configured_header_value' => $request->header($configuredHeader),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'x_real_ip' => $request->header('X-Real-IP'),
            ]);
        }

        return $ipAddress;
    }

    private function firstValidIp(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);

            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return null;
    }
}
