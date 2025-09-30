<?php

namespace App\Rules;

use App\Enum\Rental\SoRentalPaymentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

readonly class PaymentDayCheck implements ValidationRule
{
    public function __construct(private ?string $rental_payment_type) {}

    /**
     * Run the validation rule.
     *
     * @param \Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (null === $this->rental_payment_type) {
            return;
        }

        $day_labels = SoRentalPaymentType::payment_day_classes[$this->rental_payment_type]::LABELS;

        $exist = array_key_exists($value, $day_labels);

        if (!$exist) {
            $fail('付款日无效。')->translate();
        }
    }
}
