<?php

use Symfony\Component\Process\Process;

it('follows new widget messages while preserving manual scrolling and reads live page context', function () {
    $process = new Process(['node', base_path('tests/support/widget-scroll.test.cjs')]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue("widget scroll tests failed:\n".$process->getErrorOutput());
});
