<?php

namespace App\Support;

class WidgetConfig
{
    public const DEFAULTS = [
        'title' => 'Support',
        'subtitle' => 'We typically reply instantly',
        'primary_color' => '#111827',
        'accent_color' => '#2563eb',
        'background_color' => '#f3f4f6',
        'surface_color' => '#ffffff',
        'text_color' => '#111827',
        'position' => 'bottom-right',
        'offset_x' => 16,
        'offset_y' => 16,
        'border_radius' => 16,
        'panel_width' => 400,
        'launcher_size' => 56,
        'send_button_label' => 'Send',
        'input_placeholder' => 'Type your message…',
        'launcher_icon' => 'chat',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolve(?array $stored): array
    {
        $config = array_replace(self::DEFAULTS, array_filter(
            is_array($stored) ? $stored : [],
            fn ($value) => $value !== null && $value !== ''
        ));

        $config['offset_x'] = max(0, min(80, (int) $config['offset_x']));
        $config['offset_y'] = max(0, min(80, (int) $config['offset_y']));
        $config['border_radius'] = max(0, min(32, (int) $config['border_radius']));
        $config['panel_width'] = max(320, min(480, (int) $config['panel_width']));
        $config['launcher_size'] = max(44, min(72, (int) $config['launcher_size']));
        $config['position'] = in_array($config['position'], self::positions(), true)
            ? $config['position']
            : self::DEFAULTS['position'];
        $config['launcher_icon'] = in_array($config['launcher_icon'], self::icons(), true)
            ? $config['launcher_icon']
            : self::DEFAULTS['launcher_icon'];

        foreach (['primary_color', 'accent_color', 'background_color', 'surface_color', 'text_color'] as $key) {
            $config[$key] = self::normalizeColor((string) $config[$key], self::DEFAULTS[$key]);
        }

        return $config;
    }

    /**
     * @return array<string, string|int>
     */
    public static function validationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'accent_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'background_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'surface_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'text_color' => ['required', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'position' => ['required', 'in:'.implode(',', self::positions())],
            'offset_x' => ['required', 'integer', 'min:0', 'max:80'],
            'offset_y' => ['required', 'integer', 'min:0', 'max:80'],
            'border_radius' => ['required', 'integer', 'min:0', 'max:32'],
            'panel_width' => ['required', 'integer', 'min:320', 'max:480'],
            'launcher_size' => ['required', 'integer', 'min:44', 'max:72'],
            'send_button_label' => ['required', 'string', 'max:24'],
            'input_placeholder' => ['required', 'string', 'max:120'],
            'launcher_icon' => ['required', 'in:'.implode(',', self::icons())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dataAttributes(array $config): array
    {
        $config = self::resolve($config);

        return [
            'data-title' => (string) $config['title'],
            'data-subtitle' => (string) ($config['subtitle'] ?? ''),
            'data-primary-color' => (string) $config['primary_color'],
            'data-accent-color' => (string) $config['accent_color'],
            'data-background-color' => (string) $config['background_color'],
            'data-surface-color' => (string) $config['surface_color'],
            'data-text-color' => (string) $config['text_color'],
            'data-position' => (string) $config['position'],
            'data-offset-x' => (string) $config['offset_x'],
            'data-offset-y' => (string) $config['offset_y'],
            'data-border-radius' => (string) $config['border_radius'],
            'data-panel-width' => (string) $config['panel_width'],
            'data-launcher-size' => (string) $config['launcher_size'],
            'data-send-button-label' => (string) $config['send_button_label'],
            'data-input-placeholder' => (string) $config['input_placeholder'],
            'data-launcher-icon' => (string) $config['launcher_icon'],
        ];
    }

    public static function embedSnippet(string $widgetUrl, string $publicKey, array $config): string
    {
        $attributes = array_merge(
            [
                'src' => $widgetUrl,
                'data-bot-key' => $publicKey,
            ],
            self::dataAttributes($config),
        );

        $parts = [];

        foreach ($attributes as $name => $value) {
            $parts[] = $name.'="'.e($value, false).'"';
        }

        return '<script '.implode(' ', $parts).'></script>';
    }

    /**
     * @return list<string>
     */
    public static function positions(): array
    {
        return ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
    }

    /**
     * @return list<string>
     */
    public static function icons(): array
    {
        return ['chat', 'help', 'support'];
    }

    private static function normalizeColor(string $color, string $fallback): string
    {
        return preg_match('/^#([A-Fa-f0-9]{6})$/', $color) ? strtolower($color) : $fallback;
    }
}
