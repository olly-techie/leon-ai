<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\Auth;
use App\Services\PanelEngine;
use App\Services\AnswerEvaluator;

final class SessionController
{
    /**
     * POST /api/session/start
     * Body: { report_id, difficulty }
     * Returns: { session_id, next_turn }
     */
    public function start(): void
    {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input'), true);
        $reportId = $body['report_id'] ?? null;
        $difficulty = $body['difficulty'] ?? 'normal';

        if (!$reportId) {
            http_response_code(400);
            echo json_encode(['error' => 'report_id required']);
            return;
        }

        $conn = Database::conn();
        $report = $conn->fetchAssociative('SELECT * FROM reports WHERE id = ? AND user_id = ?', [$reportId, $user['sub']]);
        if (!$report) {
            http_response_code(404);
            echo json_encode(['error' => 'Report not found']);
            return;
        }

        $weaknesses = $conn->fetchAllAssociative(
            'SELECT claim, page, issue_type, severity FROM weaknesses WHERE report_id = ?',
            [$reportId]
        );

        $engine = new PanelEngine();
        $turn = $engine->firstQuestion($report['full_text'], $weaknesses, $difficulty);

        $conn->insert('sessions', [
            'report_id'  => $reportId,
            'user_id'    => $user['sub'],
            'difficulty' => $difficulty,
            'status'     => 'active',
        ]);
        $sessionId = $conn->lastInsertId();

        $conn->insert('turns', [
            'session_id'  => $sessionId,
            'examiner'    => $turn['examiner'],
            'question'    => $turn['question'],
            'cited_page'  => $turn['cited_page'],
            'is_followup' => $turn['is_followup'],
        ]);

        echo json_encode([
            'session_id' => $sessionId,
            'next_turn'  => $turn,
        ]);
    }

    /**
     * POST /api/session/answer
     * Body: { session_id, answer_text }
     * Returns: { scores, feedback, model_answer, next_turn }
     */
    public function answer(): void
    {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input'), true);
        $sessionId = $body['session_id'] ?? null;
        $answerText = trim($body['answer_text'] ?? '');

        if (!$sessionId || $answerText === '') {
            http_response_code(400);
            echo json_encode(['error' => 'session_id and answer_text required']);
            return;
        }

        $conn = Database::conn();
        $session = $conn->fetchAssociative('SELECT * FROM sessions WHERE id = ? AND user_id = ?', [$sessionId, $user['sub']]);
        if (!$session || $session['status'] !== 'active') {
            http_response_code(404);
            echo json_encode(['error' => 'Active session not found']);
            return;
        }

        // Load last unanswered turn
        $lastTurn = $conn->fetchAssociative(
            'SELECT * FROM turns WHERE session_id = ? AND answer IS NULL ORDER BY created_at DESC LIMIT 1',
            [$sessionId]
        );
        if (!$lastTurn) {
            http_response_code(400);
            echo json_encode(['error' => 'No pending question to answer']);
            return;
        }

        // Load report
        $report = $conn->fetchAssociative('SELECT * FROM reports WHERE id = ?', [$session['report_id']]);

        // Evaluate answer
        $evaluator = new AnswerEvaluator();
        $eval = $evaluator->evaluate(
            $lastTurn['question'],
            $answerText,
            $report['full_text'],
            $session['difficulty']
        );

        // Persist answer + scores
        $conn->update('turns', [
            'answer'      => $answerText,
            'accuracy'    => $eval['scores']['accuracy'],
            'evidence'    => $eval['scores']['evidence'],
            'composure'   => $eval['scores']['composure'],
            'feedback'    => $eval['feedback'],
            'model_answer'=> $eval['model_answer'],
        ], ['id' => $lastTurn['id']]);

        // Decide: end session after N turns, or generate next turn
        $turnCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM turns WHERE session_id = ? AND answer IS NOT NULL',
            [$sessionId]
        );

        $maxTurns = 6;
        if ($turnCount >= $maxTurns) {
            $conn->update('sessions', [
                'status'   => 'completed',
                'ended_at' => gmdate('c'),
            ], ['id' => $sessionId]);

            echo json_encode([
                'scores'       => $eval['scores'],
                'feedback'     => $eval['feedback'],
                'model_answer' => $eval['model_answer'],
                'next_turn'    => null,
                'session_complete' => true,
            ]);
            return;
        }

        // Load weaknesses + full turn history for orchestrator
        $weaknesses = $conn->fetchAllAssociative(
            'SELECT claim, page, issue_type, severity FROM weaknesses WHERE report_id = ?',
            [$session['report_id']]
        );
        $turnsSoFar = $conn->fetchAllAssociative(
            'SELECT examiner, question, answer, feedback FROM turns WHERE session_id = ? AND answer IS NOT NULL ORDER BY created_at',
            [$sessionId]
        );

        $engine = new PanelEngine();
        $next = $engine->nextTurn(
            $report['full_text'],
            $weaknesses,
            $turnsSoFar,
            $answerText,
            $session['difficulty']
        );

        $conn->insert('turns', [
            'session_id'  => $sessionId,
            'examiner'    => $next['examiner'],
            'question'    => $next['question'],
            'cited_page'  => $next['cited_page'],
            'is_followup' => $next['is_followup'],
        ]);

        echo json_encode([
            'scores'           => $eval['scores'],
            'feedback'         => $eval['feedback'],
            'model_answer'     => $eval['model_answer'],
            'next_turn'        => $next,
            'session_complete' => false,
        ]);
    }

    /**
     * GET /api/session/report?session_id=
     * Returns the final scorecard.
     */
    public function report(): void
    {
        $user = Auth::require();
        $sessionId = $_GET['session_id'] ?? null;
        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'session_id required']);
            return;
        }

        $conn = Database::conn();
        $session = $conn->fetchAssociative('SELECT * FROM sessions WHERE id = ? AND user_id = ?', [$sessionId, $user['sub']]);
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            return;
        }

        $turns = $conn->fetchAllAssociative(
            'SELECT examiner, question, cited_page, is_followup, answer, accuracy, evidence, composure, feedback, model_answer
             FROM turns WHERE session_id = ? AND answer IS NOT NULL ORDER BY created_at',
            [$sessionId]
        );

        // Aggregate averages
        $avg = fn(string $col) => count($turns) > 0
            ? round(array_sum(array_column($turns, $col)) / count($turns), 1)
            : 0;

        $weaknesses = $conn->fetchAllAssociative(
            'SELECT claim, page, issue_type, severity FROM weaknesses WHERE report_id = ?',
            [$session['report_id']]
        );

        // Three most dangerous: lowest-scoring answers' questions
        usort($turns, fn($a, $b) => ($a['accuracy'] + $a['evidence']) <=> ($b['accuracy'] + $b['evidence']));
        $dangerous = array_slice(array_map(fn($t) => [
            'examiner' => $t['examiner'],
            'question' => $t['question'],
            'cited_page' => $t['cited_page'],
            'your_answer' => $t['answer'],
            'model_answer' => $t['model_answer'],
        ], $turns), 0, 3);

        echo json_encode([
            'session_id'  => $sessionId,
            'difficulty'  => $session['difficulty'],
            'status'      => $session['status'],
            'averages'    => [
                'accuracy'  => $avg('accuracy'),
                'evidence'  => $avg('evidence'),
                'composure' => $avg('composure'),
                'overall'   => round(($avg('accuracy') + $avg('evidence') + $avg('composure')) / 3, 1),
            ],
            'weakness_map' => $weaknesses,
            'dangerous_questions' => $dangerous,
            'turns'       => $turns,
        ]);
    }
}
