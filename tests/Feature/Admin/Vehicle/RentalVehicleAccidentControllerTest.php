<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalVehicleAccidentController;
use App\Models\Rental\Vehicle\RentalVehicleAccident;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleAccidentControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleAccidentController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleAccidentController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRentalSaleOrdersOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([RentalVehicleAccidentController::class, 'rentalSaleOrdersOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([RentalVehicleAccidentController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleAccidentController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(RentalVehicleAccidentController::class);
        $accident   = new RentalVehicleAccident(['va_id' => 1]);

        $response = $controller->edit($accident);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalVehicleAccidentController::class);
        $accident   = new RentalVehicleAccident(['va_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $accident);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(RentalVehicleAccidentController::class);
        $accident   = new RentalVehicleAccident(['va_id' => 1]);

        $response = $controller->show($accident);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalVehicleAccidentController::class);
        $accident   = new RentalVehicleAccident(['va_id' => 1]);

        $response = $controller->destroy($accident);

        $this->assertSame(200, $response->getStatusCode());
    }
}
