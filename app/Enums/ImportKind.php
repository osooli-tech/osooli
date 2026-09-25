<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportKind: string
{
    case Gdb = 'gdb';
    case Documents = 'documents';
}
