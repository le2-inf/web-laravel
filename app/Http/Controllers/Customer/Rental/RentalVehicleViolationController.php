<?php

namespace App\Http\Controllers\Customer\Rental;

use App\Http\Controllers\Controller;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RentalVehicleViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $data = RentalVehicleViolation::customerQuery($this)
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('vv.vv_id', '<', $request->get('last_id'));
                }
            )
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
