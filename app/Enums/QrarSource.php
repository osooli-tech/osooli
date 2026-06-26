<?php

declare(strict_types=1);

namespace App\Enums;

enum QrarSource: string
{
    case Municipal = 'بلدي';
    case Engineering = 'مكتب هندسي';
    case None = 'بدون';
}
