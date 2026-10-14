<?php

use Symfony\Component\Process\Process;

it('resets deleted widget conversations and retries the question in a fresh session', function () {
    $process = new Process(['node', base_path('tests/support/widget-conversation-reset.test.cjs')]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue("widget conversation reset tests failed:\n".$process->getErrorOutput());
});
