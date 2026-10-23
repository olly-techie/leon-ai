<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

final class PdfParser
{
    /**
     * Extract text per page from a PDF file.
     * Returns array of ['page' => int, 'text' => string]
     */
    public function extractPages(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $result = [];
        foreach ($pages as $i => $page) {
            $text = trim($page->getText());
            if ($text !== '') {
                $result[] = [
                    'page' => $i + 1,
                    'text' => $text,
                ];
            }
        }
        return $result;
    }
}
