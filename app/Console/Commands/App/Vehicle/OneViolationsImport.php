<?php

namespace App\Console\Commands\App\Vehicle;

use App\Models\Rental\One\RentalOneRequest;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-violations:import',
    description: '从 vehicle_122_requests 表中获取违章信息并写入 vehicle_violations 表'
)]
class OneViolationsImport extends Command
{
    protected $signature   = '_app:one-violations:import';
    protected $description = '从 vehicle_122_requests 表中获取违章信息并写入 vehicle_violations 表';

    public function handle(): int
    {
        $this->info('开始导入违章信息...');

        $maxTurn = RentalOneRequest::query()->max('turn');

        if (!$maxTurn) {
            $this->info('vehicle_122_requests 表中没有 turn 数据。');

            return CommandAlias::SUCCESS;
        }

        $this->info("最大 turn 日期为: {$maxTurn}");

        $requests = RentalOneRequest::query()->where('status_code', '=', '200')
            ->where('turn', $maxTurn)
            ->where('key', 'like', 'violation,%')
            ->get()
        ;

        if ($requests->isEmpty()) {
            $this->info('没有需要处理的请求。');

            return CommandAlias::SUCCESS;
        }

        $rentalVehicles = RentalVehicle::all(['ve_id', 'plate_no'])->keyBy('plate_no');

        $this->info('已预加载车辆数据，开始处理违章记录...');

        DB::beginTransaction();

        try {
            foreach ($requests as $request) {
                $response = $request->response;

                // 检查响应是否为数组（已自动转换）
                if (!is_array($response)) {
                    $response = json_decode($response, true);
                }

                if (!$response || 200 != $response['code']) {
                    continue;
                }

                $violations = $response['data']['content'] ?? [];

                $violationsToUpsert = [];

                foreach ($violations as $violation) {
                    $rentalVehicle = $rentalVehicles->get($violation['hphm']);

                    // 准备 upsert 数据
                    $violationsToUpsert[] = [
                        'decision_number'    => $violation['xh'] ?? $violation['jdsbh'],
                        've_id'              => $rentalVehicle->ve_id ?? null,
                        'plate_no'           => $violation['hphm'],
                        'vu_id'              => null, // 根据需要填充
                        'violation_datetime' => Carbon::parse($violation['wfsj']),
                        'violation_content'  => $violation['wfms'] ?? '',
                        'location'           => $violation['wfdz'],
                        'fine_amount'        => floatval($violation['fkje']),
                        'penalty_points'     => intval($violation['wfjfs']),
                        'process_status'     => $violation['clbj'] ?? -1,
                        'payment_status'     => $violation['jkbj'],
                    ];
                }

                if (!empty($violationsToUpsert)) {
                    $plate_no_array = array_unique(array_column($violationsToUpsert, 'plate_no'));
                    if (count($plate_no_array) > 1) {
                        throw new \Exception('plate_no err.');
                    }

                    // 使用 upsert 插入或更新违章记录
                    $violationsToUpsertAffectRows = RentalVehicleViolation::query()->upsert(
                        $violationsToUpsert,
                        ['decision_number'], // 唯一键
                        [ // 需要更新的字段
                            've_id',
                            'plate_no',
                            'vu_id',
                            'violation_datetime',
                            'violation_content',
                            'location',
                            'fine_amount',
                            'penalty_points',
                            'process_status',
                            'payment_status',
                        ]
                    );

                    $this->info("成功 upsert {$violationsToUpsertAffectRows} 条违章记录。");
                } else {
                    $this->info('没有新的违章记录需要 upsert。');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('console')->error("处理请求ID {$request->or_id} 时出错: ".$e->getMessage());
            $this->error("处理请求ID {$request->or_id} 时出错: ".$e->getMessage());
        }

        $this->info('违章信息导入完成。');

        return CommandAlias::SUCCESS;
    }
}
