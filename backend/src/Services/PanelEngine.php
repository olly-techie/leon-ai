<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Panel engine: orchestrates the three examiner personas,
 * picks next speaker, decides follow-up vs new topic.
 */
final class PanelEngine
{
    private OpenRouter $llm;

    public const PERSONAS = [
        'hostile_external'   => 'The hostile external examiner — challenges claims, demands evidence, interrupts, is impatient with vague answers.',
        'methodology_purist' => 'The methodology purist — obsessed with sample size, data collection procedures, validity, reliability, and statistics.',
        'so_what_skeptic'    => 'The "so what?" skeptic — cares about real-world value, novelty, limitations, and future work. Dismisses incremental contributions.',
    ];

    public function __construct()
    {
        $this->llm = new OpenRouter();
    }

    /**
     * Generate the first question for a session.
     * Returns ['examiner', 'question', 'cited_page', 'is_followup']
     */
    public function firstQuestion(string $reportText, array $weaknesses, string $difficulty): array
    {
        $personaList = implode("\n", array_map(
            fn($k, $v) => "- {$k}: {$v}",
            array_keys(self::PERSONAS),
            self::PERSONAS
        ));

        $weakList = implode("\n", array_map(
            fn($w) => "- [p.{$w['page']}] ({$w['severity']}) {$w['claim']}",
            $weaknesses
        ));

        $difficultyNote = match ($difficulty) {
            'friendly' => 'Be a fair, encouraging examiner. Ask foundational questions.',
            'brutal'   => 'Be ruthless. Go straight for the weakest claims. No mercy.',
            default   => 'Be a standard, rigorous examiner.',
        };

        $system = <<<PROMPT
You are an AI examination panel for a final-year project defence.
{$difficultyNote}

The three examiners:
{$personaList}

Pick the single most dangerous opening question, attributed to the examiner best
suited to attack that weakness. The question MUST cite a specific page number
from the report using the [p.N] markers. Be specific — reference chapter,
section, table, or claim.

Return JSON with keys: examiner (one of the three IDs), question, cited_page (int), is_followup (false).
PROMPT;

        $user = "Report excerpt (key weaknesses):\n{$weakList}\n\nFull report text (truncated to first 15000 chars):\n" . substr($reportText, 0, 15000);

        $schema = [
            'name'   => 'panel_turn',
            'strict' => true,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'examiner'    => ['type' => 'string', 'enum' => array_keys(self::PERSONAS)],
                    'question'    => ['type' => 'string'],
                    'cited_page'  => ['type' => 'integer'],
                    'is_followup' => ['type' => 'boolean'],
                ],
                'required'           => ['examiner', 'question', 'cited_page', 'is_followup'],
                'additionalProperties' => false,
            ],
        ];

        $result = $this->llm->chatJson($system, $user, 'fast', $schema);
        return $this->normalizeTurn($result);
    }

    /**
     * Given the student's answer + current session state, decide next examiner turn.
     * Returns ['examiner', 'question', 'cited_page', 'is_followup']
     */
    public function nextTurn(
        string $reportText,
        array $weaknesses,
        array $turnsSoFar,
        string $lastAnswer,
        string $difficulty
    ): array {
        $personaList = implode("\n", array_map(
            fn($k, $v) => "- {$k}: {$v}",
            array_keys(self::PERSONAS),
            self::PERSONAS
        ));

        $history = '';
        foreach ($turnsSoFar as $t) {
            $history .= "Examiner ({$t['examiner']}): {$t['question']}\n";
            $history .= "Student: {$t['answer']}\n";
            if (isset($t['feedback'])) {
                $history .= "Panel note: {$t['feedback']}\n";
            }
            $history .= "\n";
        }

        $difficultyNote = match ($difficulty) {
            'friendly' => 'Be fair. If the answer is decent, move to a new topic.',
            'brutal'   => 'Interrupt weak answers with sharp follow-ups. Never let a dodge slide.',
            default   => 'Follow up on weak answers, move on when satisfied.',
        };

        $system = <<<PROMPT
You are an AI examination panel. {$difficultyNote}

The three examiners:
{$personaList}

Decide the next examiner turn. If the student's last answer was weak, vague, or
dodged the question, generate a sharp follow-up (is_followup=true) from the same
or a better-suited examiner, citing a page. If the answer was solid, move to a
new weakness or topic (is_followup=false), picking a different examiner.

Return JSON: examiner, question, cited_page (int), is_followup (bool).
PROMPT;

        $user = "Session history:\n{$history}\nLast student answer:\n{$lastAnswer}\n\nKey weaknesses:\n"
            . implode("\n", array_map(fn($w) => "- [p.{$w['page']}] {$w['claim']}", $weaknesses))
            . "\n\nReport text (first 12000 chars):\n" . substr($reportText, 0, 12000);

        $schema = [
            'name'   => 'panel_turn',
            'strict' => true,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'examiner'    => ['type' => 'string', 'enum' => array_keys(self::PERSONAS)],
                    'question'    => ['type' => 'string'],
                    'cited_page'  => ['type' => 'integer'],
                    'is_followup' => ['type' => 'boolean'],
                ],
                'required'           => ['examiner', 'question', 'cited_page', 'is_followup'],
                'additionalProperties' => false,
            ],
        ];

        $result = $this->llm->chatJson($system, $user, 'fast', $schema);
        return $this->normalizeTurn($result);
    }

    /**
     * Coerce model output: booleans may arrive as ""/0/"false",
     * cited_page as string — normalize before touching Postgres.
     */
    private function normalizeTurn(array $t): array
    {
        return [
            'examiner'    => (string) ($t['examiner'] ?? 'hostile_external'),
            'question'    => (string) ($t['question'] ?? ''),
            'cited_page'  => (int) ($t['cited_page'] ?? 1),
            'is_followup' => filter_var($t['is_followup'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
