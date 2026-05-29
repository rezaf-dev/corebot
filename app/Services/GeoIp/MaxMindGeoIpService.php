<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class MaxMindGeoIpService
{
    /**
     * @return array{country_code: ?string, country_name: ?string, city: ?string}
     */
    public function lookup(?string $ipAddress): array
    {
        $empty = [
            'country_code' => null,
            'country_name' => null,
            'city' => null,
        ];

        if ($ipAddress === null || $ipAddress === '' || ! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        $databasePath = config('corebot.geoip_database_path');

        if (! is_string($databasePath) || $databasePath === '' || ! is_readable($databasePath)) {
            return $empty;
        }

        try {
            $reader = new Reader($databasePath);
            $record = $reader->city($ipAddress);

            return [
                'country_code' => $record->country->isoCode,
                'country_name' => $record->country->name,
                'city' => $record->city->name,
            ];
        } catch (AddressNotFoundException) {
            return $empty;
        } catch (Throwable $exception) {
            Log::warning('GeoIP lookup failed.', [
                'ip' => $ipAddress,
                'message' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }
}
