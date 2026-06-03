<?php

use Illuminate\Support\Facades\Process;

it('resolves suggested prompt helpers in the widget', function () {
    $result = Process::path(base_path())
        ->run('node tests/support/widget-prompts.test.cjs');

    expect($result->successful())->toBeTrue("widget prompt tests failed:\n".$result->errorOutput());
});
