<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Enums\ImportStatus;
use PHPUnit\Framework\TestCase;

final class ImportStatusTest extends TestCase
{
    public function test_it_allows_the_forward_path(): void
    {
        $this->assertTrue(ImportStatus::Uploading->canTransitionTo(ImportStatus::Uploaded));
        $this->assertTrue(ImportStatus::Uploaded->canTransitionTo(ImportStatus::Analyzing));
        $this->assertTrue(ImportStatus::Analyzing->canTransitionTo(ImportStatus::Previewed));
        $this->assertTrue(ImportStatus::Previewed->canTransitionTo(ImportStatus::Committing));
        $this->assertTrue(ImportStatus::Committing->canTransitionTo(ImportStatus::Completed));
    }

    public function test_any_live_state_can_fail(): void
    {
        foreach ([ImportStatus::Uploading, ImportStatus::Uploaded, ImportStatus::Analyzing, ImportStatus::Previewed, ImportStatus::Committing] as $status) {
            $this->assertTrue($status->canTransitionTo(ImportStatus::Failed), $status->value.' should be able to fail');
        }
    }

    public function test_it_refuses_to_skip_the_preview(): void
    {
        $this->assertFalse(ImportStatus::Uploaded->canTransitionTo(ImportStatus::Committing));
        $this->assertFalse(ImportStatus::Analyzing->canTransitionTo(ImportStatus::Committing));
    }

    public function test_it_refuses_to_move_backwards_or_recommit(): void
    {
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Committing));
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Previewed));
        $this->assertFalse(ImportStatus::Committing->canTransitionTo(ImportStatus::Committing));
    }

    public function test_terminal_states_go_nowhere(): void
    {
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Failed));
        $this->assertFalse(ImportStatus::Failed->canTransitionTo(ImportStatus::Analyzing));
    }

    /**
     * Exhaustive test of all 49 state transitions (7x7 grid).
     * Only the 10 legal transitions return true; the other 39 return false.
     * This prevents accidental bugs like Uploading→Committing or Failed→Committing
     * from slipping through undetected.
     *
     * @dataProvider allTransitions
     */
    public function test_all_state_transitions(ImportStatus $from, ImportStatus $to, bool $isAllowed): void
    {
        $this->assertSame($isAllowed, $from->canTransitionTo($to), "Transition {$from->value} → {$to->value} should be ".($isAllowed ? 'allowed' : 'rejected'));
    }

    /**
     * Provides all 49 (from, to, isAllowed) tuples.
     * Expectations are written out by hand, not derived from the code under test.
     *
     * @return iterable<int, array{ImportStatus, ImportStatus, bool}>
     */
    public static function allTransitions(): iterable
    {
        $uploading = ImportStatus::Uploading;
        $uploaded = ImportStatus::Uploaded;
        $analyzing = ImportStatus::Analyzing;
        $previewed = ImportStatus::Previewed;
        $committing = ImportStatus::Committing;
        $completed = ImportStatus::Completed;
        $failed = ImportStatus::Failed;

        $all = [$uploading, $uploaded, $analyzing, $previewed, $committing, $completed, $failed];

        // Legal transitions (10 total)
        $legal = [
            [$uploading, $uploaded, true],
            [$uploading, $failed, true],
            [$uploaded, $analyzing, true],
            [$uploaded, $failed, true],
            [$analyzing, $previewed, true],
            [$analyzing, $failed, true],
            [$previewed, $committing, true],
            [$previewed, $failed, true],
            [$committing, $completed, true],
            [$committing, $failed, true],
        ];

        // Generate all 49 pairs and mark only the legal ones as true
        foreach ($all as $from) {
            foreach ($all as $to) {
                $isLegal = false;
                foreach ($legal as [$legalFrom, $legalTo, $_]) {
                    if ($legalFrom === $from && $legalTo === $to) {
                        $isLegal = true;
                        break;
                    }
                }
                yield [$from, $to, $isLegal];
            }
        }
    }
}
