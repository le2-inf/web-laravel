<?php

namespace App\Http\Controllers\Customer\Rental;

use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\SoOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentType;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RentalPaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RpPayStatus::labelOptions(),
        );
    }

    public function index(Request $request): Response
    {
        $this->response()->withExtras(
            RpIsValid::options(),
            RentalPaymentType::options(),
        );

        $data = RentalPayment::customerQuery($this)
            ->where('rp.is_valid', '=', RpIsValid::VALID)
            ->whereIn('so.order_status', [SoOrderStatus::SIGNED, SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('rp.rp_id', '<', $request->get('last_id'));
                }
            )
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RpPayStatus::options(),
        );
    }
}
