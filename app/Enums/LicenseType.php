<?php

namespace App\Enums;

enum LicenseType: string
{
    case Cc0 = 'CC0';
    case CcBy = 'CC BY';
    case CcBySa = 'CC BY-SA';
    case Pexels = 'Pexels License';
    case Pixabay = 'Pixabay License';
    case Mixkit = 'Mixkit License';
    case Freesound = 'Freesound (CC)';
    case UserPurchased = 'user_purchased';
    case Envato = 'Envato Elements';
    case Storyblocks = 'Storyblocks';
    case Artgrid = 'Artgrid';
    case CustomLicensed = 'Licensed';
    case Local = 'local';
}
