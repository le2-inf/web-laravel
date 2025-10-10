<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Enum\Vehicle\ViInspectionType;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleInspectionController;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalVehicleInspectionControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalVehicleInspectionController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(RentalVehicleInspectionController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRentalSaleOrdersOptionRequiresType(): void
    {
        // 缺少 inspection_type
        $resp = $this->getJson(action([RentalVehicleInspectionController::class, 'rentalSaleOrdersOption']));
        $resp->assertStatus(422);

        // 正确的 inspection_type 参数
        $resp = $this->getJson(action([RentalVehicleInspectionController::class, 'rentalSaleOrdersOption']).'?inspection_type='.ViInspectionType::DISPATCH);
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testUploadValidationError(): void
    {
        $resp = $this->postJson(action([RentalVehicleInspectionController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testDocNotFoundWhenInspectionMissing(): void
    {
        $url  = action([RentalVehicleInspectionController::class, 'doc'], ['rental_vehicle_inspection' => -1]);
        $resp = $this->getJson($url.'?mode=pdf&dt_id=0');
        $resp->assertStatus(404);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(RentalVehicleInspectionController::class);
        $inspection = new RentalVehicleInspection(['vi_id' => 1]);

        $response = $controller->show($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(RentalVehicleInspectionController::class);
        $inspection = new RentalVehicleInspection(['vi_id' => 1]);

        $response = $controller->edit($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalVehicleInspectionController::class);
        $inspection = new RentalVehicleInspection(['vi_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $inspection);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalVehicleInspectionController::class);
        $inspection = new RentalVehicleInspection(['vi_id' => 1]);

        $response = $controller->destroy($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }
}
