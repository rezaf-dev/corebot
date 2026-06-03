<?php

it('validates contact helper logic in the widget', function () {
    $result = \Illuminate\Support\Facades\Process::path(base_path())
        ->run('node tests/support/widget-contact.test.cjs');

    expect($result->successful())->toBeTrue("widget contact tests failed:\n".$result->errorOutput());
});
