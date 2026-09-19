<?php

use Symfony\Component\Process\Process;

it('batches streamed widget updates and formats markdown after streaming', function () {
    $process = new Process(['node', base_path('tests/support/widget-streaming.test.cjs')]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue("widget streaming tests failed:\n".$process->getErrorOutput());
});
