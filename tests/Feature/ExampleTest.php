<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_serves_the_public_landing_page(): void
    {
        // Renders the real landing layout, which pulls its assets through
        // Vite; the manifest exists locally but not in CI, so this stubs it
        // rather than depending on a build having run.
        $this->withoutVite();

        $this->get('/')->assertOk();
    }
}
