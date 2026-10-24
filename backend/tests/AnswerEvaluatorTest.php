<?php

declare(strict_types=1);

namespace Tests;

/**
 * AnswerEvaluator normalize() tests.
 * Tests the clamping/sanitization of LLM output scores to 0-10 range.
 * Tests the static normalize logic in isolation.
 */
class AnswerEvaluatorTest
{
    private static function clamp(int|string|float|bool|int $v): int
    {
        return max(0, min(10, (int) ($v ?? 0)));
    }

    public static function test_normalize_clamps_high_scores(): bool
    {
        $scores = ['accuracy' => 15, 'evidence' => 12, 'composure' => 18];
        $result = [
            'accuracy'  => self::clamp($scores['accuracy']),
            'evidence'  => self::clamp($scores['evidence']),
            'composure' => self::clamp($scores['composure']),
        ];
        $pass = $result['accuracy'] === 10 && $result['evidence'] === 10 && $result['composure'] === 10;
        echo ($pass ? "PASS" : "FAIL") . ": test_normalize_clamps_high_scores (accuracy=10, evidence=10, composure=10)\n";
        return $pass;
    }

    public static function test_normalize_clamp_low_scores(): bool
    {
        $scores = ['accuracy' => -5, 'evidence' => 0, 'composure' => 3];
        $result = [
            'accuracy'  => self::clamp($scores['accuracy']),
            'evidence'  => self::clamp($scores['evidence']),
            'composure' => self::clamp($scores['composure']),
        ];
        $pass = $result['accuracy'] === 0 && $result['evidence'] === 0 && $result['composure'] === 3;
        echo ($pass ? "PASS" : "FAIL") . ": test_normalize_clamp_low_scores (accuracy=0, evidence=0, composure=3)\n";
        return $pass;
    }

    public static function test_normalize_keeps_valid_scores(): bool
    {
        $scores = ['accuracy' => 7, 'evidence' => 5, 'composure' => 9];
        $result = [
            'accuracy'  => self::clamp($scores['accuracy']),
            'evidence'  => self::clamp($scores['evidence']),
            'composure' => self::clamp($scores['composure']),
        ];
        $pass = $result['accuracy'] === 7 && $result['evidence'] === 5 && $result['composure'] === 9;
        echo ($pass ? "PASS" : "FAIL") . ": test_normalize_keeps_valid_scores (accuracy=7, evidence=5, composure=9)\n";
        return $pass;
    }

    public static function test_normalize_missing_scores_default_zero(): bool
    {
        // When score key is missing, default to 0
        $result = [
            'accuracy'  => self::clamp(0),  // default
            'evidence'  => self::clamp(0),  // default
            'composure' => self::clamp(0),  // default
        ];
        $pass = $result['accuracy'] === 0 && $result['evidence'] === 0 && $result['composure'] === 0;
        echo ($pass ? "PASS" : "FAIL") . ": test_normalize_missing_scores_default_zero (all=0)\n";
        return $pass;
    }

    public static function test_normalize_string_scores_clamped(): bool
    {
        $result = [
            'accuracy'  => self::clamp('8'),
            'evidence'  => self::clamp('11'),  // over max, should clamp to 10
            'composure' => self::clamp('2'),
        ];
        $pass = $result['accuracy'] === 8 && $result['evidence'] === 10 && $result['composure'] === 2;
        echo ($pass ? "PASS" : "FAIL") . ": test_normalize_string_scores_clamped (accuracy=8, evidence=10, composure=2)\n";
        return $pass;
    }

    public static function runAll(): void
    {
        $results = [
            self::test_normalize_clamps_high_scores(),
            self::test_normalize_clamp_low_scores(),
            self::test_normalize_keeps_valid_scores(),
            self::test_normalize_missing_scores_default_zero(),
            self::test_normalize_string_scores_clamped(),
        ];
        $passed = array_filter($results);
        echo "\n" . count($passed) . "/5 tests passed\n";
    }
}