<?php
namespace App\Actions\AmoWebhooks;

use App\Repositories\LeadRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;

class AddLeadAction {
    public function __construct(
        private LeadRepository $leads
    ) {}
    public function handle($lead){
        $dt = Carbon::now();
        $timestamp = $dt->timestamp;

        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);
        $leadId = $lead['id'];

        $response = $client->patch("/api/v4/leads/{$leadId}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'json' => [
                'custom_fields_values' => [
                    [
                        'field_id' => 731973,
                        'values' => [
                            ['value' => $timestamp],
                        ],
                    ],
                ],
            ],
        ]);

        // $data = json_decode((string) $response->getBody(), true);
        $data = [
            "amo_lead_id" => $lead['id'],
            "pipeline_id" => $lead['pipeline_id'],
            "status_id" => $lead['status_id'],
            

        ];
        error_log(123);
        error_log($dt);
    }
}