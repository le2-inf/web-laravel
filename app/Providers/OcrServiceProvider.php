<?php

namespace App\Providers;

use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Support\ServiceProvider;

class OcrServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Ocrapi::class, function ($app) {
            // 工程代码泄露可能会导致 AccessKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考。
            // 建议使用更安全的 STS 方式，更多鉴权访问方式请参见：https://help.aliyun.com/document_detail/311677.html。
            $config = new Config([
                'type' => 'access_key',
                // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_ID。
                'accessKeyId' => config('setting.aliyun.key'),
                // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_SECRET。
                'accessKeySecret' => config('setting.aliyun.secret'),
            ]);
            // Endpoint 请参考 https://api.aliyun.com/product/ocr-api
            if (app()->isLocal()) {
                $config->endpoint = 'ocr-api.cn-hangzhou.aliyuncs.com';
            } else {
                $config->endpoint = 'ocr-api-vpc.cn-hangzhou.aliyuncs.com';
            }

            return new Ocrapi($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
