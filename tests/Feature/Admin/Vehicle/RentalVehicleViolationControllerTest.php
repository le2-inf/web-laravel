<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleViolationController;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleViolationControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleViolationController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleViolationController::class);
        $response   = $controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleViolationController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(RentalVehicleViolationController::class);
        $violation  = new RentalVehicleViolation(['vv_id' => 1]);

        $response = $controller->show($violation);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(RentalVehicleViolationController::class);
        $violation  = new RentalVehicleViolation(['vv_id' => 1]);

        $response = $controller->edit($violation);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalVehicleViolationController::class);
        $violation  = new RentalVehicleViolation(['vv_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $violation);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalVehicleViolationController::class);
        $violation  = new RentalVehicleViolation(['vv_id' => 1]);

        $response = $controller->destroy($violation);

        $this->assertSame(200, $response->getStatusCode());
    }
}
