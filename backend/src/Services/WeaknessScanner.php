<?php

declare(strict_types=1);

namespace App\Services;

final class WeaknessScanner
{
    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    private const ISSUE_TYPES = [
        'literature_review', 'methodology', 'sample_size', 'validity',
        'results', 'objectives', 'limitations', 'novelty', 'writing', 'other',
    ];

    private OpenRouter $llm;

    public function __construct()
    {
        $this->llm = new OpenRouter();
    }

    /**
     * Scan the full report text for weaknesses.
     * Returns list of ['claim', 'page', 'issue_type', 'severity']
     */
    public function scan(string $fullText, array $pages): array
    {
        $system = <<<'PROMPT'
You are an expert project supervisor reviewing a final-year project report.
Identify the real weaknesses an examination panel would attack: thin literature
review, unjustified sample size, missing limitations, results that do not match
objectives, weak methodology, overclaimed contributions, etc.

For each weakness return:
- claim: one-sentence description of the weakness
- page: the page number where the issue is visible (use [p.N] markers)
- issue_type: one of "literature_review", "methodology", "sample_size", "validity",
  "results", "objectives", "limitations", "novelty", "writing", "other"
- severity: "low" | "medium" | "high" | "critical"

Return 5-12 weaknesses, ordered by severity (critical first). Ground every claim
in the actual report text. Do not invent issues not supported by the document.
PROMPT;

        $user = "Full report text with page markers:\n\n" . $fullText;

        $schema = [
            'name'   => 'weakness_list',
            'strict' => true,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'weaknesses' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'claim'      => ['type' => 'string'],
                                'page'       => ['type' => 'integer'],
                                'issue_type' => ['type' => 'string'],
                                'severity'   => ['type' => 'string'],
                            ],
                            'required'           => ['claim', 'page', 'issue_type', 'severity'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required'           => ['weaknesses'],
                'additionalProperties' => false,
            ],
        ];

        $result = $this->llm->chatJson($system, $user, 'strong', $schema);
        return $this->sanitize($result['weaknesses'] ?? []);
    }

    /**
     * Coerce model output to what the weaknesses table CHECK constraints accept.
     */
    private function sanitize(array $raw): array
    {
        $out = [];
        foreach ($raw as $w) {
            if (!is_array($w)) {
                continue;
            }
            $claim = trim((string) ($w['claim'] ?? ''));
            if ($claim === '') {
                continue;
            }

            $severity = strtolower((string) ($w['severity'] ?? 'medium'));
            if (!in_array($severity, self::SEVERITIES, true)) {
                $severity = 'medium';
            }

            $issueType = strtolower((string) ($w['issue_type'] ?? 'other'));
            if (!in_array($issueType, self::ISSUE_TYPES, true)) {
                $issueType = 'other';
            }

            $out[] = [
                'claim'      => $claim,
                'page'       => max(1, (int) ($w['page'] ?? 1)),
                'issue_type' => $issueType,
                'severity'   => $severity,
            ];
        }
        return $out;
    }
}
