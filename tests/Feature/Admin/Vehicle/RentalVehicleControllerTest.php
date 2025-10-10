<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleController;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleController::class, 'create']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testStoreValidationError(): void
    {
        $payload = [];
        $resp    = $this->postJson(action([RentalVehicleController::class, 'store']), $payload);
        $resp->assertStatus(422);
    }

    public function testShowNotFound(): void
    {
        $resp = $this->getJson(action([RentalVehicleController::class, 'show'], ['rental_vehicle' => 0]));
        $resp->assertStatus(404);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(RentalVehicleController::class);
        $vehicle    = new RentalVehicle(['ve_id' => 1]);

        $response = $controller->edit($vehicle);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalVehicleController::class);
        $vehicle    = new RentalVehicle(['ve_id' => 1]);

        $response = $controller->destroy($vehicle);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalVehicleController::class);
        $vehicle    = new RentalVehicle(['ve_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $vehicle);
    }

    public function testUploadRequiresFileAndFieldName(): void
    {
        $resp = $this->postJson(action([RentalVehicleController::class, 'upload']), []);
        $resp->assertStatus(422);

        // even with wrong field_name should be 422
        $resp = $this->postJson(action([RentalVehicleController::class, 'upload']), [
            'field_name' => 'invalid_field',
        ]);
        $resp->assertStatus(422);
    }
}
