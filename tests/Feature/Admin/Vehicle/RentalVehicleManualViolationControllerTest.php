<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleManualViolationController;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleManualViolationControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleManualViolationController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleManualViolationController::class);
        $response   = $controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleManualViolationController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowReturnsResponse(): void
    {
        $controller  = app(RentalVehicleManualViolationController::class);
        $manualEntry = new RentalVehicleManualViolation(['vmv_id' => 1]);

        $response = $controller->show($manualEntry);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller  = app(RentalVehicleManualViolationController::class);
        $manualEntry = new RentalVehicleManualViolation(['vmv_id' => 1]);

        $response = $controller->edit($manualEntry);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller  = app(RentalVehicleManualViolationController::class);
        $manualEntry = new RentalVehicleManualViolation(['vmv_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $manualEntry);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller  = app(RentalVehicleManualViolationController::class);
        $manualEntry = new RentalVehicleManualViolation(['vmv_id' => 1]);

        $response = $controller->destroy($manualEntry);

        $this->assertSame(200, $response->getStatusCode());
    }
}
