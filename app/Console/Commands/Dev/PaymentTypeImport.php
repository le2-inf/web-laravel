<?php

namespace App\Console\Commands\Dev;

use App\Enum\Payment\RpPtId;
use App\Models\Payment\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_dev:payment-type:import',
    description: ''
)]
class PaymentTypeImport extends Command
{
    protected $signature   = '_dev:payment-type:import';
    protected $description = '';

    public function handle(): int
    {
        $this->info('开始更新 payment-type...');

        DB::transaction(function () {
            $query = PaymentType::query();

            $exists_is_active = $query->pluck('is_active', 'pt_name')->toArray();

            // 获取所有枚举值
            $enumValues = array_keys(RpPtId::LABELS);

            // 删除不在枚举中的数据
            $query->whereNotIn('pt_id', $enumValues)->delete();

            $upsert = [];
            foreach (RpPtId::LABELS as $key => $label) {
                $upsert[] = [
                    'pt_id'     => $key,
                    'pt_name'   => $pt_name = $label,
                    'required'  => in_array($key, RpPtId::defaultRequiredTypes),
                    'is_active' => ($exists_is_active[$pt_name] ?? false) || in_array($key, array_merge(RpPtId::defaultActiveTypes, RpPtId::defaultRequiredTypes)),
                ];
            }
            if ($upsert) {
                $query->upsert(
                    $upsert,
                    ['pt_id'],
                );
            }
        });

        $this->info('更新 payment-type 完毕。');

        return CommandAlias::SUCCESS;
    }
}
