<?php

namespace App\Observers;

use App\Enum\Rental\IoIoType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Exceptions\ClientException;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Payment\RentalPaymentInout;

class RentalPaymentObserver
{
    public function created(RentalPayment $rentalPayment): void {}

    public function updated(RentalPayment $rentalPayment): void
    {
        if (RpIsValid::VALID !== $rentalPayment->is_valid->value) {
            throw new ClientException('无效财务信息');
        }

        $original = $rentalPayment->getOriginal();

        $occur_amount = $account = null;

        if (RpPayStatus::PAID === $rentalPayment->pay_status->value) { // 未支付→支付 支付→支付
            $occur_amount = bcsub($rentalPayment->actual_pay_amount, $original['actual_pay_amount'] ?? '0', 2);

            $account = $rentalPayment->RentalPaymentAccount;
            if (!$account) {
                return;
            }

            $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoIoType::IN : IoIoType::OUT;
        } elseif (RpPayStatus::PAID === $original['pay_status']->value && RpPayStatus::UNPAID === $rentalPayment->pay_status->value) { // 回退， 支付→未支付
            // 更新账户金额
            // 写一笔反向记录

            $occur_amount = bcsub('0', $original['actual_pay_amount'], 2);

            $account = RentalPaymentAccount::query()->find($original['pa_id']);

            $io_type = bccomp($occur_amount, '0', '2') > 0 ? IoIoType::OUT_ : IoIoType::IN_;
        }

        if (null !== $occur_amount) {
            $lastRentalMoneyIo = RentalPaymentInout::query()->where('pa_id', '=', $account->pa_id)->orderByDesc('io_id')->first();

            if (0 !== bccomp($lastRentalMoneyIo?->account_balance ?? '0', $account->pa_balance, 2)) {
                throw new ClientException('金额错误-before');
            }

            $RentalMoneyIo = RentalPaymentInout::query()->create([
                'io_type'         => $io_type,
                'cu_id'           => $rentalPayment->RentalSaleOrder->cu_id,
                'pa_id'           => $account->pa_id,
                'occur_datetime'  => now(),
                'occur_amount'    => $occur_amount,
                'account_balance' => bcadd($lastRentalMoneyIo->account_balance ?? '0', $occur_amount, 2),
                'rp_id'           => $rentalPayment->rp_id,
            ]);

            // 更新钱包总金额

            $account->pa_balance = bcadd($account->pa_balance, $occur_amount, 2);

            if (0 !== bccomp($RentalMoneyIo->account_balance, $account->pa_balance, 2)) {
                throw new ClientException('金额错误-after');
            }

            $account->save();
        }
    }

    /**
     * Handle the RentalPayment "deleted" event.
     */
    public function deleted(RentalPayment $rentalPayment): void {}

    /**
     * Handle the RentalPayment "restored" event.
     */
    public function restored(RentalPayment $rentalPayment): void {}

    /**
     * Handle the RentalPayment "force deleted" event.
     */
    public function forceDeleted(RentalPayment $rentalPayment): void {}
}
