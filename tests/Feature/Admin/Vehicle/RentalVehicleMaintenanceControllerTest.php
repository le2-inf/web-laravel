<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleMaintenanceController;
use App\Models\Rental\Vehicle\RentalVehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleMaintenanceControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleMaintenanceController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleMaintenanceController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRentalSaleOrdersOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([RentalVehicleMaintenanceController::class, 'rentalSaleOrdersOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([RentalVehicleMaintenanceController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleMaintenanceController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller  = app(RentalVehicleMaintenanceController::class);
        $maintenance = new RentalVehicleMaintenance(['vm_id' => 1]);

        $response = $controller->edit($maintenance);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller  = app(RentalVehicleMaintenanceController::class);
        $maintenance = new RentalVehicleMaintenance(['vm_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $maintenance);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller  = app(RentalVehicleMaintenanceController::class);
        $maintenance = new RentalVehicleMaintenance(['vm_id' => 1]);

        $response = $controller->destroy($maintenance);

        $this->assertSame(200, $response->getStatusCode());
    }
}
