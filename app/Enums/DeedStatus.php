<?php

declare(strict_types=1);

namespace App\Enums;

enum DeedStatus: string
{
    case Updated = 'محدث';
    case Old = 'قديم';
}
