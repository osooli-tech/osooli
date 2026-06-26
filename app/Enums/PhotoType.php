<?php

declare(strict_types=1);

namespace App\Enums;

enum PhotoType: string
{
    case Aerial = 'جوية';
    case Ground = 'أرضية';
}
