<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Evaluates a student's answer against the report text.
 * Scores: accuracy, evidence, composure (0-10 each) + feedback + model answer.
 */
final class AnswerEvaluator
{
    private OpenRouter $llm;

    public function __construct()
    {
        $this->llm = new OpenRouter();
    }

    /**
     * Returns ['scores' => ['accuracy','evidence','composure'], 'feedback', 'model_answer']
     */
    public function evaluate(
        string $question,
        string $answer,
        string $reportText,
        string $difficulty
    ): array {
        $difficultyNote = match ($difficulty) {
            'friendly' => 'Be encouraging but honest. Credit partial understanding.',
            'brutal'   => 'Hold to a high standard. Vague answers score poorly.',
            default   => 'Be fair and rigorous.',
        };

        $system = <<<PROMPT
You are an expert examiner scoring a student's answer at a project defence. {$difficultyNote}

Score the answer on three axes (0-10 integers):
- accuracy: Is the content factually correct per the report?
- evidence: Does the student cite specific pages, chapters, data, or sources?
- composure: Is the answer clear, confident, and well-structured?

Give specific feedback on what was weak or strong.
Write a model answer: a concise, defence-grade response the student should aspire to,
grounded in the report.

Return JSON: scores {accuracy, evidence, composure}, feedback (string), model_answer (string).
PROMPT;

        $user = "Examiner question:\n{$question}\n\nStudent answer:\n{$answer}\n\nReport text (first 12000 chars, for grounding):\n" . substr($reportText, 0, 12000);

        $schema = [
            'name'   => 'answer_evaluation',
            'strict' => true,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'scores' => [
                        'type'       => 'object',
                        'properties' => [
                            'accuracy'  => ['type' => 'integer'],
                            'evidence'  => ['type' => 'integer'],
                            'composure' => ['type' => 'integer'],
                        ],
                        'required'           => ['accuracy', 'evidence', 'composure'],
                        'additionalProperties' => false,
                    ],
                    'feedback'     => ['type' => 'string'],
                    'model_answer' => ['type' => 'string'],
                ],
                'required'           => ['scores', 'feedback', 'model_answer'],
                'additionalProperties' => false,
            ],
        ];

        $result = $this->llm->chatJson($system, $user, 'strong', $schema);
        return $this->normalize($result);
    }

    /**
     * Coerce model output: scores may arrive as strings or out of range,
     * which would trip the turns table CHECK constraints.
     */
    private function normalize(array $result): array
    {
        $clamp = fn($v) => max(0, min(10, (int) ($v ?? 0)));
        $scores = is_array($result['scores'] ?? null) ? $result['scores'] : [];

        return [
            'scores' => [
                'accuracy'  => $clamp($scores['accuracy'] ?? 0),
                'evidence'  => $clamp($scores['evidence'] ?? 0),
                'composure' => $clamp($scores['composure'] ?? 0),
            ],
            'feedback'     => (string) ($result['feedback'] ?? ''),
            'model_answer' => (string) ($result['model_answer'] ?? ''),
        ];
    }
}
