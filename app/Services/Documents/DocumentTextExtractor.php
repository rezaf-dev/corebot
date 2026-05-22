<?php

namespace App\Services\Documents;

use App\Models\KnowledgeSource;
use Illuminate\Support\Facades\Storage;

class DocumentTextExtractor
{
    public function __construct(
        private readonly PdfTextExtractor $pdfs,
        private readonly DocxExtractionClient $docx,
    ) {}

    public function extract(KnowledgeSource $source): string
    {
        $text = match ($source->type) {
            'text', 'faq' => (string) $source->raw_text,
            'pdf' => $this->pdfs->extract(Storage::path($source->original_file_path)),
            'docx' => $this->docx->extract(Storage::path($source->original_file_path)),
            default => throw new \RuntimeException('Unsupported knowledge source type.'),
        };

        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        if (str_word_count(strip_tags($text)) < 20) {
            throw new \RuntimeException('Knowledge source text is too short to index.');
        }

        return trim($text);
    }
}
