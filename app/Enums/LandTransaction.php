<?php

declare(strict_types=1);

namespace App\Enums;

enum LandTransaction: string
{
    case Sold = 'مباعة';
    case Rented = 'مؤجرة';
    case ForSale = 'قيد البيع';
    case Private = 'خاصة';
}
