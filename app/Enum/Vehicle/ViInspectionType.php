<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class ViInspectionType extends EnumLikeBase
{
    public const string DISPATCH = 'dispatch';
    public const string RETURN   = 'return';

    public const array LABELS = [
        self::DISPATCH => '发车',
        self::RETURN   => '退车',
    ];
}
