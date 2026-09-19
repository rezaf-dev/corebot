<?php

use Symfony\Component\Process\Process;

it('uses the Laravel deferred callback helper when a global defer function exists', function () {
    $process = new Process([
        PHP_BINARY,
        base_path('tests/support/laravel-defer-collision.php'),
    ]);
    $process->run();

    expect($process->isSuccessful())
        ->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('laravel-defer-ok');

    foreach ([
        app_path('Services/AI/OpenAIService.php'),
        app_path('Services/Rag/ChatAnswerService.php'),
    ] as $servicePath) {
        expect(file_get_contents($servicePath))->toContain('use function Illuminate\Support\defer;');
    }
});
