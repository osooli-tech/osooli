<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetType: string
{
    case Land = 'أرض';
    case Apartment = 'شقة';
    case Building = 'عمارة';
    case Villa = 'فيلا';
    case Warehouse = 'مستودع';
}
