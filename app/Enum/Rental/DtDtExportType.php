<?php

namespace App\Enum\Rental;

use App\Enum\EnumLikeBase;

class DtDtExportType extends EnumLikeBase
{
    public const string DOCX = 'docx';
    public const string PDF  = 'pdf';

    public const array LABELS = [
        self::DOCX => 'word文件',
        self::PDF  => 'PDF文件',
    ];
}
