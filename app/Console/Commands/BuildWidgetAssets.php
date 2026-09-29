<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Support\WidgetAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('widgets:build')]
#[Description('Generate static JavaScript assets for every active widget')]
class BuildWidgetAssets extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WidgetAsset $widgetAsset): int
    {
        $count = 0;
        $publicKeys = [];

        Bot::query()
            ->where('status', 'active')
            ->eachById(function (Bot $bot) use ($widgetAsset, &$count, &$publicKeys): void {
                $widgetAsset->sync($bot);
                $publicKeys[] = $bot->public_key;
                $count++;
            });

        $widgetAsset->prune($publicKeys);

        $this->components->info("Generated {$count} widget asset(s).");

        return self::SUCCESS;
    }
}
