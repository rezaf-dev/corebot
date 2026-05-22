<?php

use App\Mail\SupportRequestMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('sends support request emails to the configured inbox', function () {
    config(['corebot.support_request_email' => 'support@corefixlab.com']);

    Mail::fake();

    $this->from('/')
        ->post(route('support-requests.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'company' => 'Acme CRM',
            'type' => 'customization',
            'message' => 'We need help installing corebot for our CRM.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(SupportRequestMail::class, function (SupportRequestMail $mail) {
        return $mail->hasTo('support@corefixlab.com')
            && $mail->request['email'] === 'jane@example.com'
            && $mail->request['type'] === 'customization';
    });
});

it('rejects support requests when no inbox is configured', function () {
    config(['corebot.support_request_email' => null]);

    $this->post(route('support-requests.store'), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'type' => 'support',
        'message' => 'Hello',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('generates support request urls with a subfolder app url', function () {
    config(['app.url' => 'https://app.test/corebot']);
    URL::forceRootUrl(config('app.url'));

    expect(route('support-requests.store'))->toBe('https://app.test/corebot/support-requests');
});

it('validates support request fields', function () {
    config(['corebot.support_request_email' => 'support@example.com']);

    $this->post(route('support-requests.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'type', 'message']);
});
