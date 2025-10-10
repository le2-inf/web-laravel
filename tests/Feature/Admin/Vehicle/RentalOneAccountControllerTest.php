<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\RentalOneAccountController;
use App\Models\Rental\One\RentalOneAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class RentalOneAccountControllerTest extends TestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([RentalOneAccountController::class, 'index']));
        $resp->assertStatus(200);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([RentalOneAccountController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowNotFound(): void
    {
        $resp = $this->getJson(action([RentalOneAccountController::class, 'show'], ['rental_one_account' => 0]));
        $resp->assertStatus(404);
    }

    public function testCreateReturnsResponse(): void
    {
        $resp = $this->getJson(action([RentalOneAccountController::class, 'create']));

        $resp->assertStatus(200);
    }

    public function testEditReturnsResponse(): void
    {
        RentalOneAccount::factory()->create(['']);

        $resp = $this->getJson(action([RentalOneAccountController::class, 'edit'], [1]));

        $resp->assertStatus(200);
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(RentalOneAccountController::class);
        $account    = new RentalOneAccount(['oa_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $account);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(RentalOneAccountController::class);
        $account    = new RentalOneAccount(['oa_id' => 1]);

        $response = $controller->destroy($account);

        $this->assertSame(200, $response->getStatusCode());
    }
}
