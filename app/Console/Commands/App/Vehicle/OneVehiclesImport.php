<?php

namespace App\Console\Commands\App\Vehicle;

use App\Models\Rental\One\RentalOneRequest;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-vehicles:import',
    description: '从 vehicle_122_requests 表中获取车辆信息并写入 vehicles 表'
)]
class OneVehiclesImport extends Command
{
    protected $signature   = '_app:one-vehicles:import {--turn=}';
    protected $description = '从 vehicle_122_requests 表中获取车辆信息并写入 vehicles 表';

    public function handle(): int
    {
        $this->info('开始导入车辆信息...');

        $turn = $this->option('turn') ?: RentalOneRequest::query()->max('turn');

        if (!$turn) {
            $this->info('vehicle_122_requests 表中没有 turn 数据。');

            return CommandAlias::SUCCESS;
        }

        $this->info("最大 turn 日期为: {$turn}");

        $requests = RentalOneRequest::query()->where('status_code', '=', '200')
            ->where('turn', $turn)
            ->where('key', 'like', 'vehs,%')
            ->get()
        ;

        if ($requests->isEmpty()) {
            $this->info('没有需要处理的请求。');

            return CommandAlias::SUCCESS;
        }

        DB::transaction(function () use ($requests) {
            /** @var RentalOneRequest $request */
            foreach ($requests as $request) {
                $response = $request->response;

                // 检查响应是否为数组（已自动转换）
                if (!is_array($response)) {
                    $response = json_decode($response, true);
                }

                if (!$response || 200 != $response['code']) {
                    continue;
                }

                $rentalVehiclesJson = $response['data']['content'] ?? [];

                $rentalVehiclesToUpsert = [];

                foreach ($rentalVehiclesJson as $rentalVehicleJson) {
                    // 准备 upsert 数据
                    $rentalVehiclesToUpsert[] = [
                        'plate_no' => $rentalVehicleJson['hphm'],
                        've_type'  => $rentalVehicleJson['hpzl'],
                    ];
                }

                if (!empty($rentalVehiclesToUpsert)) {
                    // 使用 upsert 插入或更新车辆记录
                    $rentalVehiclesToUpsertAffectRows = RentalVehicle::query()->upsert(
                        $rentalVehiclesToUpsert,
                        ['plate_no'], // 唯一键
                        [ // 需要更新的字段
                            'plate_no',
                            've_type',
                        ]
                    );

                    $this->info("成功 upsert {$rentalVehiclesToUpsertAffectRows} 条车辆记录。");
                } else {
                    $this->info('没有新的车辆记录需要 upsert。');
                }
            }
        });

        $this->info('车辆信息导入完成。');

        return CommandAlias::SUCCESS;
    }
}
