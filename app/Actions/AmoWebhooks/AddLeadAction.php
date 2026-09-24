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

        // Обновляем Дата Время создания
        $client->patch("/api/v4/leads/{$leadId}", [
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

        // Запрашиваем все поля сделки
        // $response = $client->get("/api/v4/leads/{$leadId}?with=source", [
        //     'headers' => [
        //         'Authorization' => "Bearer {$token}",
        //     ],
        // ]);

        // $leadData = json_decode((string) $response->getBody(), true);
        
        // // Выдёргиваем source_phone АТС
        // $targetFields = ['Source phone', 'Номер sipuni'];
        // $sourcePhone = collect($leadData['custom_fields_values'] ?? [])
        //     ->first(function ($field) use ($targetFields) {
        //         $name = $field['field_name'] ?? null;
        //         return in_array($name, $targetFields, true);
        //     })['values'][0]['value'] ?? null;

        // // $advertisingSources = [
        // //     "89539396999"=>"Сайт",
        // //     "89210802077" => "ВК"
        // // ]
        // $createdAtFromLead = collect($leadData['custom_fields_values'])
        //     ->first(function ($field) {
        //         return $field['field_id'] === 731973;
        //     })['values'][0]['value'];
        // $data = [
        //     "amo_lead_id" => $leadData['id'],
        //     "pipeline_id" => $leadData['pipeline_id'],
        //     "status_id" => $leadData['status_id'],
        //     "source_phone" => $sourcePhone,
        //     "net_profit" => 0,
        //     "created_at"=> Carbon::createFromTimestamp($timestamp)->toDateTimeString(),
        //     "amo_source_name" => $leadData['_embedded']['source']['name'] ?? null,
        //     "amo_source_id" => $leadData['_embedded']['source']['id'] ?? null
        // ];
        // error_log($dt);
        // $this->leads->add($data);
    }
}