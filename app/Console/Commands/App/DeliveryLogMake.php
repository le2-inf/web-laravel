<?php

namespace App\Console\Commands\App;

use App\Enum\Delivery\DcDcStatus;
use App\Enum\Delivery\DlSendStatus;
use App\Models\Delivery\DeliveryChannel;
use App\Models\Delivery\DeliveryLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;

class DeliveryLogMake extends Command
{
    protected $signature = 'app:delivery-log-make';

    protected $description = 'Command description';

    public function handle(): int
    {
        DB::transaction(function () {
            /** @var array<DeliveryChannel> $DeliveryChannels */
            $DeliveryChannels = DeliveryChannel::query()->where('dc_status', '=', DcDcStatus::ENABLED)->get();

            foreach ($DeliveryChannels as $DeliveryChannel) {
                $method = 'make_'.$DeliveryChannel->dc_key;
                if (method_exists($DeliveryChannel, $method)) {
                    $DeliveryChannel->{$method}();
                }
            }
        });

        DeliveryLog::query()
            ->where('send_status', '!=', DlSendStatus::ST_DELIVERED)
            ->where('send_attempt', '<=', 3)
            ->where('scheduled_for', '>', now()->subDays(7)) // 超过7天就不发送了
            ->orderBy('dl_id')
            ->with('DeliveryChannel')
            ->chunk(100, function ($logs) {
                /** @var DeliveryLog $log */
                foreach ($logs as $log) {
                    $method = 'send_'.$log->DeliveryChannel->dc_provider;

                    if (!method_exists($log, $method)) {
                        throw new \RuntimeException("{$method} 方法不存在");
                    }

                    $log->update([
                        'send_attempt'      => new Expression('send_attempt + 1'),
                        'scheduled_sent_at' => Carbon::now(),
                        'send_status'       => DlSendStatus::ST_SENDING,
                    ]);

                    try {
                        $resp_body = $log->{$method}();

                        $log->update([
                            'scheduled_delivered_at' => Carbon::now(),
                            'send_status'            => DlSendStatus::ST_DELIVERED,
                            'resp_body'              => $resp_body,
                        ]);
                    } catch (\Throwable $exception) {
                        report($exception);

                        $log->update([
                            'send_status'           => DlSendStatus::ST_FAILED,
                            'resp_error_code'       => class_basename($exception),
                            'resp_error_message'    => $exception->getMessage(),
                            'scheduled_canceled_at' => Carbon::now(),
                        ]);
                    }
                }
            })
        ;

        return CommandAlias::SUCCESS;
    }
}
