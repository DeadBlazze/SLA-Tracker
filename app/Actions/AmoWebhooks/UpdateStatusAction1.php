<?php
namespace App\Actions\AmoWebhooks;

use Illuminate\Support\Facades\Log;
use App\Jobs\updateLeadStatusJob;
use App\Repositories\LeadRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;

class UpdateStatusAction1{
    public function __construct(
    ) {}
    public function handle($lead){
        $leadId = !empty($lead['id']) ? (int) $lead['id'] : null;
        if(!$leadId) return Log::warning('AmoWebhook: Lead payload without valid ID', ['payload' => $lead]);
        Log::info('Queue Worker Debug Info', [
            'php_binary' => PHP_BINARY,
            'php_version' => PHP_VERSION,
            'php_ini' => php_ini_loaded_file(),
            'curl_cainfo' => ini_get('curl.cainfo'),
            'openssl_cafile' => ini_get('openssl.cafile'),
        ]);
        updateLeadStatusJob::dispatch($lead);
        return;
    }
}