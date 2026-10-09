<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // public/build is not in the repository, so a page rendered in CI has
        // no Vite manifest to read. Tests check behaviour, not asset tags.
        $this->withoutVite();
    }
}
