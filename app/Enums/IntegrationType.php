<?php

namespace App\Enums;

enum IntegrationType: string
{
    case Webhook = 'webhook';
    case CreateLead = 'create_lead';
    case SendEmail = 'send_email';
    case HttpGet = 'http_get';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
