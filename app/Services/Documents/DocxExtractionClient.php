<?php

namespace App\Services\Documents;

use Symfony\Component\Process\Process;

class DocxExtractionClient
{
    public function extract(string $path): string
    {
        $realPath = realpath($path);

        if (! $realPath || pathinfo($realPath, PATHINFO_EXTENSION) !== 'docx') {
            throw new \RuntimeException('Invalid DOCX file.');
        }

        $script = base_path('scripts/extract_docx.py');
        $python = config('corebot.docx_python') ?: 'python3';
        $process = new Process([$python, $script, $realPath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'DOCX extraction failed.');
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw new \RuntimeException('No extractable text was found in the DOCX.');
        }

        return $text;
    }
}
