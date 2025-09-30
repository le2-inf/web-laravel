<?php

namespace App\Http\Controllers\Customer\Risk;

use App\Http\Controllers\Controller;
use App\Models\Rental\Risk\RentalExpiryVehicle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RentalExpiryVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $data = RentalExpiryVehicle::customerQueryWithOrderVeId($this, $request)
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
