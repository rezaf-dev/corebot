<?php

use Aws\MockHandler;
use Aws\Result;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Mail;

it('sends application email through SES with configured AWS credentials', function (?string $token) {
    $environment = Env::getRepository();
    $originalToken = $environment->get('AWS_SESSION_TOKEN');

    try {
        $environment->set('AWS_SESSION_TOKEN', $token ?? '');
        $services = require config_path('services.php');
    } finally {
        if ($originalToken === null) {
            $environment->clear('AWS_SESSION_TOKEN');
        } else {
            $environment->set('AWS_SESSION_TOKEN', $originalToken);
        }
    }

    $handler = new MockHandler;
    $handler->append(new Result(['MessageId' => 'ses-test-message-id']));

    config([
        'mail.default' => 'ses',
        'mail.from.address' => 'sender@example.com',
        'mail.from.name' => 'Corebot',
        'services.ses' => array_merge($services['ses'], [
            'key' => 'test-access-key',
            'secret' => 'test-secret-key',
            'region' => 'eu-west-1',
            'handler' => $handler,
        ]),
    ]);

    Mail::purge('ses');

    $transport = Mail::mailer()->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(SesTransport::class)
        ->and($transport->ses()->getRegion())->toBe('eu-west-1');

    $credentials = $transport->ses()->getCredentials()->wait();

    expect($credentials->getAccessKeyId())->toBe('test-access-key')
        ->and($credentials->getSecretKey())->toBe('test-secret-key')
        ->and($credentials->getSecurityToken())->toBe($token);

    $sent = Mail::raw('SES delivery test', function (Message $message): void {
        $message->to('recipient@example.com')->subject('SES test');
    });

    $command = $handler->getLastCommand();

    expect($command->getName())->toBe('SendRawEmail')
        ->and($command['Source'])->toContain('sender@example.com')
        ->and($command['Destinations'])->toBe(['recipient@example.com'])
        ->and($command['RawMessage']['Data'])->toContain('SES delivery test')
        ->and($sent->getSymfonySentMessage()->getOriginalMessage()->getHeaders()->get('X-SES-Message-ID')->getBodyAsString())
        ->toBe('ses-test-message-id');
})->with([
    'long-lived credentials' => [null],
    'temporary credentials' => ['test-session-token'],
]);
