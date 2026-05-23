<?php

use App\Services\Documents\DocxExtractionClient;
use Illuminate\Support\Facades\Config;

it('defaults docx python config to null so the client uses python3', function () {
    expect(config('corebot.docx_python'))->toBeNull();
});

it('extracts docx text using the configured python executable', function () {
    $fakePython = base_path('tests/fixtures/fake_docx_python.sh');
    chmod($fakePython, 0755);

    Config::set('corebot.docx_python', $fakePython);

    $docx = tempnam(sys_get_temp_dir(), 'corebot-docx-');
    rename($docx, $docx.'.docx');
    $docx .= '.docx';

    try {
        $text = app(DocxExtractionClient::class)->extract($docx);

        expect($text)->toContain('Fixture paragraph one');
    } finally {
        @unlink($docx);
    }
});

it('rejects non-docx paths', function () {
    $txt = tempnam(sys_get_temp_dir(), 'corebot-txt-');

    try {
        expect(fn () => app(DocxExtractionClient::class)->extract($txt))
            ->toThrow(RuntimeException::class, 'Invalid DOCX file.');
    } finally {
        @unlink($txt);
    }
});
