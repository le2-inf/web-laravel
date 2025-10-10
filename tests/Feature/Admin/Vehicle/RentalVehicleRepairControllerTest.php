<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleRepairController;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleRepairControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleRepairController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleRepairController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRentalSaleOrdersOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([RentalVehicleRepairController::class, 'rentalSaleOrdersOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([RentalVehicleRepairController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleRepairController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(RentalVehicleRepairController::class);
        $repair     = new RentalVehicleRepair(['vr_id' => 1]);

        $response = $controller->edit($repair);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalVehicleRepairController::class);
        $repair     = new RentalVehicleRepair(['vr_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $repair);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalVehicleRepairController::class);
        $repair     = new RentalVehicleRepair(['vr_id' => 1]);

        $response = $controller->destroy($repair);

        $this->assertSame(200, $response->getStatusCode());
    }
}
