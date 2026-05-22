<?php

namespace App\Services\Documents;

use Spatie\PdfToText\Pdf;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $text = Pdf::getText($path);

        if (blank(trim($text))) {
            throw new \RuntimeException('No extractable text was found in the PDF.');
        }

        return $text;
    }
}
