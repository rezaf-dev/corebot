<?php

use Illuminate\Support\Facades\Process;

it('translates frontend copy across all six languages and localizes widget controls', function () {
    $result = Process::path(base_path())->run('node tests/support/localization.test.mjs');

    expect($result->successful())->toBeTrue("Localization checks failed:\n".$result->errorOutput());
});
