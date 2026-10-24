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

        $maxBytes = $_ENV['MAX_UPLOAD_SIZE'] ?? 15728640; // 15MB default
        if (($file['size'] ?? 0) > $maxBytes) {
            http_response_code(413);
            echo json_encode(['error' => 'PDF must be 15MB or smaller']);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if ($ext !== 'pdf' || $mime !== 'application/pdf') {
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

        // Run weakness scan before any DB writes so a failed scan leaves no orphan
        $scanner = new WeaknessScanner();
        $weaknesses = $scanner->scan($fullText, $pages);

        $conn = Database::conn();
        $conn->beginTransaction();
        try {
            $reportId = $conn->executeQuery(
                'INSERT INTO reports (user_id, filename, full_text, pages)
                 VALUES (:user_id, :filename, :full_text, :pages) RETURNING id',
                [
                    'user_id'   => $user['sub'],
                    'filename'  => $file['name'],
                    'full_text' => $fullText,
                    'pages'     => json_encode($pages),
                ]
            )->fetchOne();

            foreach ($weaknesses as $w) {
                $conn->insert('weaknesses', [
                    'report_id'  => $reportId,
                    'claim'      => $w['claim'],
                    'page'       => $w['page'],
                    'issue_type' => $w['issue_type'],
                    'severity'   => $w['severity'],
                ]);
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        echo json_encode([
            'report_id'   => $reportId,
            'weaknesses'  => $weaknesses,
        ]);
    }
}
