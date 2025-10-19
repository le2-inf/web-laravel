<?php

namespace App\Console\Commands\App\Vehicle;

use App\Enum\One\OaOaType;
use App\Models\Rental\One\RentalOneAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-cookie:refresh',
    description: 'Refresh cookies for 122.gov.cn service'
)]
class OneRefreshCookie extends Command
{
    private const REQUEST_HEADERS = [
        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language'           => 'zh',
        'Cache-Control'             => 'no-cache',
        'Connection'                => 'keep-alive',
        'Pragma'                    => 'no-cache',
        'Referer'                   => 'https://gab.122.gov.cn/',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent'                => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
    ];

    protected $signature   = '_app:one-cookie:refresh';
    protected $description = 'Refresh cookies for 122.gov.cn service';

    public function handle(): int
    {
        $accounts = RentalOneAccount::query()
            ->where(function (Builder $query) {
                $query->whereNull('cookie_refresh_at')
                    ->orWhere('cookie_refresh_at', '>=', now()->subMinutes(30))
                ;
            })
            ->whereRaw('LENGTH(cookie_string) > ?', [30])
            ->orderBy('cookie_refresh_at', 'DESC')
            ->get()
        ;

        foreach ($accounts as $account) {
            switch ($account->oa_type) {
                case OaOaType::PERSON:
                    $this->processPerson($account);

                    break;

                case OaOaType::COMPANY:
                    $this->processCompany($account);

                    break;

                default:
                    break;
            }
        }

        return CommandAlias::SUCCESS;
    }

    private function processPerson(RentalOneAccount $rentalOneAccount)
    {
        $client    = new Client(['cookies' => true]);
        $cookieJar = $rentalOneAccount->initializeCookies();

        $domain = $rentalOneAccount->oa_province_value['url'];

        $location = $domain.'/views/member/';

        $requestCount = 1;

        $filePath = null;

        while ($location && $requestCount <= 10) {
            $response = $this->makeRequest($rentalOneAccount, $client, $location, $cookieJar, $filePath);

            $location = $response->getHeaderLine('Location');
            ++$requestCount;
        }

        if ($filePath && $this->findWelcome($filePath, $searchString = '欢迎')) {
            Log::channel('console')->info('Response Body contain : '.$searchString);

            $rentalOneAccount->cookie_refresh_at = now();
            $rentalOneAccount->save();

            Storage::delete($filePath);
        } else {
            $rentalOneAccount->cookie_string = null;
            $rentalOneAccount->save();
        }
    }

    private function findWelcome($filePath, $searchString): bool
    {
        $command = "grep -n1 '".escapeshellcmd($searchString)."' ".escapeshellarg(Storage::path($filePath));

        $output     = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        Log::channel('console')->info('grep result : '.join(PHP_EOL, $output));

        return 0 === $returnCode;
    }

    private function makeRequest(RentalOneAccount $rentalOneAccount, Client $client, string $url, FileCookieJar $cookieJar, &$filePath)
    {
        Log::channel('console')->info("Request URL: {$url}");

        $domain = $rentalOneAccount->oa_province_value['url'];

        $header = self::REQUEST_HEADERS;

        $header['Referer'] = $domain.'/';

        $response = $client->get($url, [
            'headers'         => $header,
            'cookies'         => $cookieJar,
            'allow_redirects' => false,
            'debug'           => fopen(storage_path(sprintf('logs/122-%d-%s.log', $rentalOneAccount->oa_id, date('Y-m-d'))), 'a+'),
        ]);

        $statusCode = $response->getStatusCode();

        //        if (!app()->isProduction()) {
        if (200 === $statusCode) {
            $filePath = sprintf('html/response_body_%s.html', date('YmdHisv'));
            Storage::put($filePath, $response->getBody());
        }
        //        }

        return $response;
    }

    private function processCompany(RentalOneAccount $rentalOneAccount)
    {
        $client    = new Client();
        $cookieJar = $rentalOneAccount->initializeCookies();

        $domain = $rentalOneAccount->oa_province_value['url'];

        $location = $domain.'/';

        $requestCount = 1;

        $filePath = null;

        while ($location && $requestCount <= 10) {
            $response = $this->makeRequest($rentalOneAccount, $client, $location, $cookieJar, $filePath);

            $location = $response->getHeaderLine('Location');
            ++$requestCount;
        }

        if ($filePath && $this->findWelcome($filePath, $searchString = '欢迎')) {
            Log::channel('console')->info('Response Body contain : '.$searchString);

            $rentalOneAccount->cookie_refresh_at = now();
            $rentalOneAccount->save();

            Storage::delete($filePath);
        } else {
            $rentalOneAccount->cookie_string = null;
            $rentalOneAccount->save();
        }
    }
}
