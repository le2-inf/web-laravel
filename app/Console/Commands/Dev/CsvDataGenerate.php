<?php

namespace App\Console\Commands\Dev;

use App\Enum\Customer\CuCuType;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoRentalType;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\ViInspectionType;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderController;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Customer\RentalCustomerIndividual;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Payment\RentalPaymentInout;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleSettlement;
use App\Models\Rental\Sale\RentalVehicleReplacement;
use App\Models\Rental\Vehicle\RentalServiceCenter;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleForceTake;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleInsurance;
use App\Models\Rental\Vehicle\RentalVehicleMaintenance;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehiclePreparation;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Models\Rental\Vehicle\RentalVehicleSchedule;
use App\Models\Rental\Vehicle\RentalVehicleUsage;
use App\Services\ProgressDisplay;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[\AllowDynamicProperties]
#[AsCommand(name: '_dev:csv-data:generate', description: 'Command description')]
class CsvDataGenerate extends Command
{
    protected $signature   = '_dev:csv-data:generate {--month=1-12}';
    protected $description = 'Command description';

    public function handle(): void
    {
        ini_set('memory_limit', '2G');

        $startTime = microtime(true);
        $this->info('start time: '.date('Y-m-d H:i:s'));

        [$month_form,$month_to] = explode('-', $this->option('month'));

        // 先删除数据

        DB::transaction(function () {
            RentalVehicleSchedule::query()->delete();
            RentalVehicleManualViolation::query()->delete();
            RentalVehicleMaintenance::query()->delete();
            RentalVehicleInsurance::query()->delete();
            RentalSaleSettlement::query()->delete();
            RentalVehicleRepair::query()->delete();
            RentalVehicleUsage::query()->delete();
            RentalVehicleInspection::query()->delete();
            RentalVehicleReplacement::query()->delete();
            RentalServiceCenter::query()->delete();

            RentalPaymentAccount::query()->update(['pa_balance' => '0']);
            RentalPaymentInout::query()->delete();
            RentalPayment::query()->delete();
            RentalSaleOrder::query()->delete();
            RentalVehiclePreparation::query()->delete();

            RentalVehicleForceTake::query()->delete();

            RentalCustomerIndividual::query()->whereRaw("cu_id not in (select cu_id from rental_customers where contact_name like '演示%')")->delete();
            RentalCustomer::query()->whereNotLike('contact_name', '演示%')->delete();

            RentalVehicle::query()->update(['status_service' => VeStatusService::YES, 'status_rental' => VeStatusRental::LISTED, 'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED]);

            DB::statement('call reset_identity_sequences_in_schema(?)', ['public']);
        });

        /** @var Collection $rentalServiceCenters */
        $rentalServiceCenters = null;
        DB::transaction(function () use (&$rentalServiceCenters) {
            RentalServiceCenter::factory()->count(5)->create();

            $rentalServiceCenters = RentalServiceCenter::query()->where('status', '=', ScScStatus::ENABLED)->get();

            /** @var Collection $rentalCustomers */
            $rentalCustomers = RentalCustomer::factory()->count(20)->create();
            foreach ($rentalCustomers as $rentalCustomer) {
                if (CuCuType::INDIVIDUAL === $rentalCustomer->cu_type) {
                    RentalCustomerIndividual::factory()->for($rentalCustomer)->create();
                }
            }
        });

        $RentalVehicles  = RentalVehicle::query()->get();
        $rentalCustomers = RentalCustomer::query()->get();

        for ($month = $month_form; $month <= $month_to; ++$month) {
            config(['setting.gen.month.current' => $month]);

            $this->info(str_repeat('#', 80));
            $this->info(sprintf("# \033[32mGenerating data for month %s \033[0m", $month));
            $this->info(str_repeat('#', 80));

            DB::transaction(function () use ($RentalVehicles, $rentalCustomers, $rentalServiceCenters) {
                $factor = config('setting.gen.factor');

                // ---------- group customer ----------
                $progressDisplay = new ProgressDisplay($factor, 'customer');
                for ($length = 0; $length < $factor; ++$length) {
                    $progressDisplay->displayProgress($length);

                    /** @var RentalVehicle $RentalVehicle */
                    $RentalVehicle = $RentalVehicles->random();

                    /** @var RentalCustomer $rentalCustomer */
                    $rentalCustomer = $rentalCustomers->random();

                    RentalVehiclePreparation::factory()->for($RentalVehicle)->create();

                    /** @var RentalSaleOrder $RentalSaleOrder */
                    $RentalSaleOrder = RentalSaleOrder::factory()->for($RentalVehicle)->for($rentalCustomer)->create();

                    if (SoRentalType::LONG_TERM === $RentalSaleOrder->rental_type->value) {
                        $payments = RentalSaleOrderController::callRentalPaymentsOption($RentalSaleOrder->toArray());
                        foreach ($payments as $payment) {
                            $RentalSaleOrder->RentalPayments()->create($payment);
                        }
                    } elseif (SoRentalType::SHORT_TERM === $RentalSaleOrder->rental_type->value) {
                        if (in_array($RentalSaleOrder->order_status->value, SoOrderStatus::getSignAndAfter)) {
                            $types = RpPtId::getFeeTypes(SoRentalType::SHORT_TERM);
                            foreach ($types as $type) {
                                if (fake()->boolean(75)) {
                                    RentalPayment::factory()->for($RentalSaleOrder)->create(['pt_id' => $type]);
                                }
                            }
                        }
                    }

                    switch ($RentalSaleOrder->order_status) {
                        case SoOrderStatus::PENDING:
                            $RentalVehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::RESERVED, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case SoOrderStatus::CANCELLED:
                            $RentalVehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::LISTED, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;

                        case SoOrderStatus::SIGNED:
                            $RentalVehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::RENTED, status_dispatch: VeStatusDispatch::DISPATCHED);

                            break;

                        case SoOrderStatus::COMPLETED:
                        case SoOrderStatus::EARLY_TERMINATION:
                            $RentalVehicle->updateStatus(status_service: VeStatusService::YES, status_rental: VeStatusRental::PENDING, status_dispatch: VeStatusDispatch::NOT_DISPATCHED);

                            break;
                    }

                    if (SoOrderStatus::PENDING === $RentalSaleOrder->order_status) {
                        RentalVehicleReplacement::factory()->for($RentalSaleOrder)->for($RentalVehicle, 'CurrentVehicle')->for($RentalVehicles->random(), 'NewVehicle')->create();
                    }

                    if (in_array($RentalSaleOrder->order_status, [SoOrderStatus::SIGNED, SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])) {
                        for ($groupSize = 0; $groupSize < 2; ++$groupSize) {
                            $rentalVehicleInspection1 = RentalVehicleInspection::factory()->for($RentalVehicle)->for($RentalSaleOrder)->create(['inspection_type' => ViInspectionType::DISPATCH]);
                            $rentalVehicleInspection2 = RentalVehicleInspection::factory()->for($RentalVehicle)->for($RentalSaleOrder)->create(['inspection_type' => ViInspectionType::RETURN]);

                            $RentalVehicleUsage = RentalVehicleUsage::factory()->for($RentalSaleOrder)->for($RentalVehicle)->for($rentalVehicleInspection1, 'RentalVehicleInspectionStart')->for($rentalVehicleInspection2, 'RentalVehicleInspectionEnd')->create();
                        }

                        RentalVehicleRepair::factory()->for($RentalVehicle)->for($RentalSaleOrder)->for($rentalServiceCenters->random())->create();
                        RentalVehicleMaintenance::factory()->for($RentalVehicle)->for($RentalSaleOrder)->for($rentalServiceCenters->random())->create();

                        RentalVehicleManualViolation::factory()->for($RentalVehicle)->for($RentalVehicleUsage)->create();
                    }

                    if (in_array($RentalSaleOrder->order_status, [SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])) {
                        RentalSaleSettlement::factory()->for($RentalSaleOrder)->create();
                    }

                    RentalVehicleInsurance::factory()->for($RentalVehicle)->create();

                    RentalVehicleSchedule::factory()->for($RentalVehicle)->create();
                }
            });

            $endTime = microtime(true);
            $this->info('end time: '.date('Y-m-d H:i:s'));
            $this->info(sprintf('elapsed time: %.2f s', $endTime - $startTime));
        }
    }
}
