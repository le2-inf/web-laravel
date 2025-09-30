<?php

namespace App\Enum\Customer;

use App\Enum\EnumLikeBase;

class CuCuType extends EnumLikeBase
{
    public const string INDIVIDUAL = 'individual';
    public const string COMPANY    = 'company';

    public const array LABELS = [
        self::INDIVIDUAL => '个人',
        self::COMPANY    => '公司',
    ];
}
