<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportKind;
use Illuminate\Contracts\Container\Container;

final class ImporterFactory
{
    public function __construct(private readonly Container $container) {}

    public function for(ImportKind $kind): Importer
    {
        return match ($kind) {
            ImportKind::Gdb => $this->container->make(GdbImporter::class),
            ImportKind::Documents => $this->container->make(DocumentImporter::class),
        };
    }
}
