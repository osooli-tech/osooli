<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Database\Dialect;
use App\Support\DatabaseEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseEnumTest extends TestCase
{
    use RefreshDatabase;

    /** On PostgreSQL the labels come from pg_enum; the PHP list must match it exactly. */
    public function test_the_declared_labels_match_what_the_migrations_create(): void
    {
        if (! Dialect::isPostgres()) {
            $this->markTestSkipped('Enum types exist only on PostgreSQL.');
        }

        foreach (DatabaseEnum::TYPES as $type) {
            DatabaseEnum::forget($type);
            $this->assertSame(DatabaseEnum::DECLARED[$type], DatabaseEnum::labels($type), $type);
        }
    }

    public function test_every_dropdown_has_labels_on_the_current_database(): void
    {
        foreach (array_keys(DatabaseEnum::TYPES) as $column) {
            $this->assertNotSame([], DatabaseEnum::for($column), $column);
            $this->assertStringStartsWith('in:', DatabaseEnum::rule($column));
        }
    }
}
