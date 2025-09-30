<?php

namespace App\Models\Rental;

trait ImportTrait
{
    public static array $fields = [];

    abstract public static function importColumns(): array;

    abstract public static function importBeforeValidateDo(): \Closure;

    abstract public static function importValidatorRule(array $item, array $fieldAttributes): void;

    abstract public static function importAfterValidatorDo(): \Closure;

    abstract public static function importCreateDo(): \Closure;
}
