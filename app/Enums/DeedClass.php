<?php

declare(strict_types=1);

namespace App\Enums;

enum DeedClass: string
{
    case Agricultural = 'زراعي';
    case Residential = 'سكني';
    case Industrial = 'صناعي';
}
