<?php

namespace App\Enums;

enum LicenseType: string
{
    case Cc0 = 'CC0';
    case CcBy = 'CC BY';
    case CcBySa = 'CC BY-SA';
    case Pexels = 'Pexels License';
    case Pixabay = 'Pixabay License';
    case UserPurchased = 'user_purchased';
    case Local = 'local';
}
