<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\Auth;
use App\Services\PdfParser;
use App\Services\WeaknessScanner;

final class UploadController
{
    public function upload(): void
    {
        $user = Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No PDF uploaded or upload error']);
            return;
        }

        $file = $_FILES['pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            http_response_code(400);
            echo json_encode(['error' => 'File must be a PDF']);
            return;
        }

        $parser = new PdfParser();
        $pages = $parser->extractPages($file['tmp_name']);

        if (empty($pages)) {
            http_response_code(422);
            echo json_encode(['error' => 'Could not extract text from PDF (may be scanned/image-based)']);
            return;
        }

        $fullText = implode("\n\n", array_map(
            fn(array $p) => "[p.{$p['page']}] {$p['text']}",
            $pages
        ));

        $conn = Database::conn();
        $conn->insert('reports', [
            'user_id'    => $user['sub'],
            'filename'   => $file['name'],
            'full_text'  => $fullText,
            'pages'      => json_encode($pages),
        ]);
        $reportId = $conn->lastInsertId();

        // Run weakness scan
        $scanner = new WeaknessScanner();
        $weaknesses = $scanner->scan($fullText, $pages);

        foreach ($weaknesses as $w) {
            $conn->insert('weaknesses', [
                'report_id'  => $reportId,
                'claim'      => $w['claim'],
                'page'       => $w['page'],
                'issue_type' => $w['issue_type'],
                'severity'   => $w['severity'],
            ]);
        }

        echo json_encode([
            'report_id'   => $reportId,
            'weaknesses'  => $weaknesses,
        ]);
    }
}
