<?php

namespace App\Http\Controllers\Customer\Rental;

use App\Attributes\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\Rental\Vehicle\RentalVehicleAccident;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RentalVehicleAccidentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $data = RentalVehicleAccident::customerQuery($this)
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('va.va_id', '<', $request->get('last_id'));
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
