<?php

it('renders the demo page without a bot key', function () {
    config(['corebot.demo_bot_public_key' => null]);

    $this->get(route('demo'))
        ->assertSuccessful()
        ->assertSee('DEMO_BOT_PUBLIC_KEY', false)
        ->assertDontSee('data-bot-key', false);
});

it('embeds the demo widget when a bot key is configured', function () {
    config(['corebot.demo_bot_public_key' => 'bot_demo123']);

    $this->get(route('demo'))
        ->assertSuccessful()
        ->assertSee('data-bot-key="bot_demo123"', false);
});
