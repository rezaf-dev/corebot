<?php

namespace App\Support;

use App\Models\Bot;
use App\Models\ChatConversation;

class BotContactConfig
{
    public const FIELD_NAME = 'name';

    public const FIELD_EMAIL = 'email';

    public const FIELD_PHONE = 'phone';

    public const DEFAULT_FIELDS = [self::FIELD_NAME, self::FIELD_EMAIL];

    public const DEFAULT_REQUIRED = [self::FIELD_EMAIL];

    public const FIELD_MAP = [
        self::FIELD_NAME => 'visitor_name',
        self::FIELD_EMAIL => 'visitor_email',
        self::FIELD_PHONE => 'visitor_phone',
    ];

    /**
     * @return list<string>
     */
    public static function fields(Bot $bot): array
    {
        $fields = $bot->contact_fields;

        if (! is_array($fields) || $fields === []) {
            return self::DEFAULT_FIELDS;
        }

        return array_values(array_filter(
            $fields,
            fn (string $field): bool => isset(self::FIELD_MAP[$field]),
        ));
    }

    /**
     * @return list<string>
     */
    public static function required(Bot $bot): array
    {
        $required = $bot->contact_required;

        if (! is_array($required)) {
            return self::DEFAULT_REQUIRED;
        }

        return array_values(array_filter(
            $required,
            fn (string $field): bool => in_array($field, self::fields($bot), true),
        ));
    }

    public static function hasCompleteContact(Bot $bot, ChatConversation $conversation): bool
    {
        $required = self::required($bot);

        if ($required === [] || self::fields($bot) === []) {
            return true;
        }

        foreach ($required as $field) {
            $column = self::FIELD_MAP[$field];
            $value = $conversation->{$column};

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string|null>
     */
    public static function contactPayload(ChatConversation $conversation): array
    {
        return [
            'visitor_name' => $conversation->visitor_name,
            'visitor_email' => $conversation->visitor_email,
            'visitor_phone' => $conversation->visitor_phone,
        ];
    }

    /**
     * @return array{name?: string, email?: string, phone?: string}
     */
    public static function widgetContact(ChatConversation $conversation): array
    {
        $contact = [];

        if ($conversation->visitor_name) {
            $contact['name'] = $conversation->visitor_name;
        }

        if ($conversation->visitor_email) {
            $contact['email'] = $conversation->visitor_email;
        }

        if ($conversation->visitor_phone) {
            $contact['phone'] = $conversation->visitor_phone;
        }

        return $contact;
    }
}
