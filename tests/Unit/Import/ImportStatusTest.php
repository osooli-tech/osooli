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
}
