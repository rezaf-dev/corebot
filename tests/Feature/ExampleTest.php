<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('uses the corebot app branding', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('corebot')
        ->assertSee('logo.svg');
});
