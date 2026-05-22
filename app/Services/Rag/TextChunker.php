<?php

namespace App\Services\Rag;

class TextChunker
{
    public function chunk(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $words = preg_split('/\s+/', $text, flags: PREG_SPLIT_NO_EMPTY);

        if (! $words || count($words) < 80) {
            return count($words ?? []) > 0 ? [implode(' ', $words)] : [];
        }

        $chunks = [];
        $target = 800;
        $overlap = 100;

        for ($start = 0; $start < count($words); $start += ($target - $overlap)) {
            $slice = array_slice($words, $start, $target);

            if (count($slice) < 80 && $chunks) {
                $chunks[array_key_last($chunks)] .= ' '.implode(' ', $slice);
                break;
            }

            $chunks[] = implode(' ', $slice);

            if ($start + $target >= count($words)) {
                break;
            }
        }

        return $chunks;
    }
}
