<?php

namespace Tests\Http\Controllers\Admin;

use App\Enum\Config\ImportConfig;
use App\Http\Controllers\Admin\Config\ImportController;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class ImportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var RentalCustomer $rentalCustomer */
        $rentalCustomer = RentalCustomer::query()->where('contact_name', '=', '苏妹妹')->first();
        if ($rentalCustomer) {
            DB::transaction(function () use ($rentalCustomer) {
                $rentalCustomer->RentalCustomerIndividual()->delete();
                $rentalCustomer->RentalCustomerCompany()->delete();
                $rentalCustomer->delete();
            });
        }
        RentalVehicle::query()->whereIn('plate_no', ['川N7JF90'])->delete();
        $RentalSaleOrder = RentalSaleOrder::query()->whereLike('contract_number', 'TMP%')->first();
        if ($RentalSaleOrder) {
            DB::transaction(function () use ($RentalSaleOrder) {
                $RentalSaleOrder->RentalPayments()->delete();
                $RentalSaleOrder->delete();
            });
        }
    }

    public function testUpdate()
    {
        foreach (ImportConfig::keys() as $model) {
            $model_name = class_basename($model);

            $filename = "template_{$model_name}.xlsx";
            $path     = base_path("tests/Files/{$filename}");
            $this->assertFileExists($path, '请确认文件存在');

            $file = new UploadedFile(
                $path,                // 本地文件路径
                $filename,         // 客户端原始文件名
                null,          // MIME 类型
                null,                 // 文件大小（null 则自动从文件读取）
                true                  // 是否为 test 模式（绕过 is_uploaded_file 检查）
            );

            $response_upload = $this->postJson(
                action([ImportController::class, 'upload']),
                ['field_name' => 'import_file', 'file' => $file]
            );

            $response_upload_data = $response_upload->original['data'];

            $response11 = $this->putJson(
                action([ImportController::class, 'update']),
                [
                    'model'       => $model,
                    'import_file' => [
                        'name'    => 'a',
                        'extname' => 'xlsx',
                        'size'    => '123',
                        'path_'   => $response_upload_data['filepath'],
                    ],
                ]
            );
            $response11->assertStatus(200);

            // $this->assertDatabaseHas('rental_customers', ['contact_name' => '苏妹妹']);
        }
    }

    public function testTemplateDownload()
    {
        foreach (ImportConfig::keys() as $model) {
            $resp = $this->getJson(
                action([ImportController::class, 'template'], ['model' => $model]),
            );
            $resp->assertStatus(200)
                ->assertJsonStructure(['data' => ['url']])
            ;
            echo json_encode($resp->original)."\n";
        }
    }

    public function testShow()
    {
        $resp = $this->getJson(
            action([ImportController::class, 'show'])
        );
        $resp->assertOk();
        //                    ->assertJsonStructure(['data' => ['url']]);
        echo json_encode($resp->original)."\n";
    }
}
