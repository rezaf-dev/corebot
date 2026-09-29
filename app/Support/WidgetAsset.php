<?php

namespace App\Support;

use App\Models\Bot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class WidgetAsset
{
    private const BOOTSTRAP_PLACEHOLDER = '/* __COREBOT_BOOTSTRAP__ */ null';

    public function sync(Bot $bot): void
    {
        $bot->refresh();

        if (! $bot->isActive()) {
            $this->delete($bot);

            return;
        }

        $runtime = File::get(public_path('widget.js'));

        if (! str_contains($runtime, self::BOOTSTRAP_PLACEHOLDER)) {
            throw new RuntimeException('Widget bootstrap placeholder is missing from the runtime.');
        }

        $bootstrap = json_encode($this->bootstrap($bot), $this->jsonFlags());
        $contents = Str::replaceFirst(self::BOOTSTRAP_PLACEHOLDER, $bootstrap, $runtime);
        $path = $this->path($bot->public_key);

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $contents);
    }

    public function delete(Bot $bot): void
    {
        File::delete($this->path($bot->public_key));
    }

    /** @param list<string> $activePublicKeys */
    public function prune(array $activePublicKeys): void
    {
        $activeFilenames = array_map(fn (string $publicKey): string => $this->filename($publicKey), $activePublicKeys);
        $directory = (string) config('corebot.widget_assets.path');

        foreach (File::glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.js') as $path) {
            if (! in_array(basename($path), $activeFilenames, true)) {
                File::delete($path);
            }
        }
    }

    public function url(Bot $bot): string
    {
        return $this->urlForKey($bot->public_key);
    }

    public function urlForKey(string $publicKey): string
    {
        return url('/widgets/'.$this->filename($publicKey));
    }

    /** @return array<string, mixed> */
    private function bootstrap(Bot $bot): array
    {
        return [
            'bot_key' => $bot->public_key,
            'api_base' => url('/api/public/chat'),
            'widget' => array_merge($bot->resolvedWidgetConfig(), [
                'welcome_message' => $bot->welcome_message,
            ]),
            'contact_fields' => BotContactConfig::fields($bot),
            'contact_required' => BotContactConfig::required($bot),
            'collect_contact_on_start' => (bool) $bot->collect_contact_on_start,
        ];
    }

    private function path(string $publicKey): string
    {
        return rtrim((string) config('corebot.widget_assets.path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$this->filename($publicKey);
    }

    private function filename(string $publicKey): string
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $publicKey)) {
            throw new RuntimeException('Bot public key contains invalid filename characters.');
        }

        return $publicKey.'.js';
    }

    private function jsonFlags(): int
    {
        return JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR;
    }
}
