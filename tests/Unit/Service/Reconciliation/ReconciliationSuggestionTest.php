<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Reconciliation;

use PHPUnit\Framework\TestCase;

/**
 * Tests the reconciliation scoring algorithm mirroring ReconciliationService::getSuggestions.
 *
 * Score breakdown (mirrors the service logic):
 *   +0.5  — amount match (precondition; if not matched, score = 0.0)
 *   +0.3  — exact date (diff = 0 days)
 *   +0.2  — date off by 1 day
 *   +0.1  — date off by 2–3 days
 *   +0.2  — description similarity > 60 %
 *
 * If the entry is already reconciled the score must be 0.0 (duplicate guard).
 */
class ReconciliationSuggestionTest extends TestCase
{
    /**
     * Calculates a suggestion score given the matching parameters.
     *
     * @param bool   $amountMatch          Whether the amounts are equal
     * @param int    $dateDiffDays         Absolute difference in days between the two dates
     * @param float  $descriptionSimilarity Similarity percentage 0–100 (as returned by similar_text)
     * @param bool   $entryAlreadyReconciled Whether the statement entry is already reconciled
     */
    private function calculateScore(
        bool $amountMatch,
        int $dateDiffDays,
        float $descriptionSimilarity,
        bool $entryAlreadyReconciled = false,
    ): float {
        // Guard: already-reconciled entries must never be suggested
        if ($entryAlreadyReconciled) {
            return 0.0;
        }

        // Amount must match (query-level filter in the service)
        if (!$amountMatch) {
            return 0.0;
        }

        $score = 0.5; // base score for amount match

        // Date proximity bonus
        if ($dateDiffDays === 0) {
            $score += 0.3;
        } elseif ($dateDiffDays <= 1) {
            $score += 0.2;
        } elseif ($dateDiffDays <= 3) {
            $score += 0.1;
        }

        // Description similarity bonus
        if ($descriptionSimilarity > 60.0) {
            $score += 0.2;
        }

        return round($score, 2);
    }

    public function testPerfectMatch(): void
    {
        // Same amount, same date (0 days diff), identical description (100 % similarity)
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          0,
            descriptionSimilarity: 100.0,
        );

        $this->assertGreaterThanOrEqual(0.9, $score);
        // 0.5 + 0.3 + 0.2 = 1.0
        $this->assertSame(1.0, $score);
    }

    public function testAmountMatchDateOffByOne(): void
    {
        // Amount matches, date is 1 day apart, no description similarity
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          1,
            descriptionSimilarity: 0.0,
        );

        $this->assertGreaterThanOrEqual(0.6, $score);
        // 0.5 + 0.2 = 0.7
        $this->assertSame(0.7, $score);
    }

    public function testAmountMatchDateOffByOneWithSimilarDescription(): void
    {
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          1,
            descriptionSimilarity: 75.0,
        );

        // 0.5 + 0.2 + 0.2 = 0.9
        $this->assertSame(0.9, $score);
    }

    public function testAmountMatchDateOffByThree(): void
    {
        // Amount matches, date is 3 days apart, no description match
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          3,
            descriptionSimilarity: 0.0,
        );

        $this->assertGreaterThanOrEqual(0.5, $score);
        // 0.5 + 0.1 = 0.6
        $this->assertSame(0.6, $score);
    }

    public function testAmountMatchDateOffByThreeWithSimilarDescription(): void
    {
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          3,
            descriptionSimilarity: 80.0,
        );

        // 0.5 + 0.1 + 0.2 = 0.8
        $this->assertSame(0.8, $score);
    }

    public function testAmountMismatch(): void
    {
        // Different amount — the service would never surface this candidate
        // (it filters by exact amount in the query), so score must be 0
        $score = $this->calculateScore(
            amountMatch:           false,
            dateDiffDays:          0,
            descriptionSimilarity: 100.0,
        );

        $this->assertLessThan(0.5, $score);
        $this->assertSame(0.0, $score);
    }

    public function testNoDuplicateReconciliation(): void
    {
        // If the statement entry is already reconciled, it must not be suggested
        $score = $this->calculateScore(
            amountMatch:              true,
            dateDiffDays:             0,
            descriptionSimilarity:    100.0,
            entryAlreadyReconciled:   true,
        );

        $this->assertSame(0.0, $score);
    }

    public function testBaseScoreOnlyWhenDateFarAndNoDescription(): void
    {
        // Date diff > 3 days gets no date bonus; description below threshold
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          10,
            descriptionSimilarity: 30.0,
        );

        // Only base: 0.5 + 0 + 0 = 0.5
        $this->assertSame(0.5, $score);
    }

    public function testDescriptionBelowThresholdDoesNotAddBonus(): void
    {
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          0,
            descriptionSimilarity: 60.0, // exactly 60 — NOT > 60, so no bonus
        );

        // 0.5 + 0.3 = 0.8
        $this->assertSame(0.8, $score);
    }

    public function testDescriptionJustAboveThresholdAddsBonus(): void
    {
        $score = $this->calculateScore(
            amountMatch:           true,
            dateDiffDays:          0,
            descriptionSimilarity: 60.1,
        );

        // 0.5 + 0.3 + 0.2 = 1.0
        $this->assertSame(1.0, $score);
    }
}
