<?php

use Illuminate\Support\Facades\Process;

it('formats assistant markdown safely in the widget', function () {
    $result = Process::path(base_path())
        ->run('node tests/support/widget-markdown.test.cjs');

    expect($result->successful())->toBeTrue("widget markdown tests failed:\n".$result->errorOutput());
});
