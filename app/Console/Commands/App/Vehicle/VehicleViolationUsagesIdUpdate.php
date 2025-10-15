<?php

namespace App\Console\Commands\App\Vehicle;

use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:vehicle-violation-vu-id:update',
    description: '更新 *_violations,*_manual_violations 表中的 vu_id 字段，每次处理指定数量的记录'
)]
class VehicleViolationUsagesIdUpdate extends Command
{
    protected $signature   = '_app:vehicle-violation-vu-id:update';
    protected $description = '更新 *_violations,*_manual_violations 表中的 vu_id 字段，每次处理指定数量的记录';

    public function handle(): int
    {
        $this->info('开始更新 vu_id...');

        DB::transaction(function () {
            $batchSize = 1000;

            foreach ([
                ['vmv_id', RentalVehicleManualViolation::query()],
                ['vv_id', RentalVehicleViolation::query()],
            ] as $class) {
                [$pk,$query] = $class;

                /**
                 * @var Builder $query
                 */
                $lastId = 0; // 初始最大ID

                while (true) {
                    // 查询下一个批次的记录，$pk 大于上一个批次的最大ID
                    $violations = $query->clone()->where($pk, '>', $lastId)
                        ->orderBy($pk)
                        ->limit($batchSize)
                        ->get()
                    ;

                    if ($violations->isEmpty()) {
                        break;
                    }

                    $this->info("处理违章记录块，包含 {$violations->count()} 条记录。");

                    // 提取当前批次中的所有 ve_id 和 violation_datetime
                    $rentalVehicleIds = $violations->pluck('ve_id')->unique();

                    // 查询当前批次涉及的所有 VehicleUsage 记录
                    $usages = DB::query()
                        ->from('rental_vehicle_usages', 'vu')
                        ->leftJoin('rental_vehicle_inspections as vi1', 'vi1.vi_id', '=', 'vu.start_vi_id')
                        ->leftJoin('rental_vehicle_inspections as vi2', 'vi2.vi_id', '=', 'vu.end_vi_id')
                        ->select('vu.*', 'vi1.inspection_datetime as vu_start_dt', 'vi2.inspection_datetime as vu_end_dt')
                        ->whereIn('vu.ve_id', $rentalVehicleIds)
                        ->whereNotNull('vu.end_vi_id')
                        ->orderBy('vu.ve_id')
                        ->orderBy('vi1.inspection_datetime')
                        ->get()
                    ;

                    $this->info("已获取相关的 VehicleUsage 记录，共 {$usages->count()} 条。");

                    // 将 VehicleUsage 按 ve_id 分组并排序
                    $usagesGrouped = [];
                    foreach ($usages as $usage) {
                        $usagesGrouped[$usage->ve_id][] = $usage;
                    }

                    foreach ($violations as $violation) {
                        $pk_id              = $violation->{$pk};
                        $ve_id              = $violation->ve_id;
                        $violation_datetime = $violation->violation_datetime;

                        // 更新最后处理的 ID
                        if ($lastId < $pk_id) {
                            $lastId = $pk_id;
                        }

                        if (!isset($usagesGrouped[$ve_id])) {
                            continue;
                        }

                        $matchedUsage = null;
                        $usagesList   = $usagesGrouped[$ve_id];

                        // 从当前索引开始查找，避免重复遍历
                        foreach ($usagesList as $usage) {
                            if ($usage->vu_start_dt <= $violation_datetime && $usage->vu_end_dt >= $violation_datetime) {
                                $matchedUsage = $usage;

                                break;
                            }
                        }

                        if ($matchedUsage) {
                            $violation->vu_id = $matchedUsage->vu_id;
                            $violation->save();
                        }
                    }
                }
            }
        });

        $this->info('所有违章记录已处理完毕。');

        return CommandAlias::SUCCESS;
    }
}
