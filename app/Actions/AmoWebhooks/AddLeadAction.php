<?php
namespace App\Actions\AmoWebhooks;

use App\Repositories\LeadRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Services\amoCRM\ResolveLeadSourceService;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Facades\Log;
use App\Support\AmoPipelines;

class AddLeadAction {
    public function __construct(
        private LeadRepository $leads,
        private ResolveLeadSourceService $leadSourceResolver
    ) {}

    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];

    public function handle($lead){
        // Запрашиваем все поля сделки
        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);
        $leadId = $lead['id'];
        $response = $client->get("/api/v4/leads/{$leadId}?with=source", [
            'headers' => [
            'Authorization' => "Bearer {$token}",
            ],
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode === 204 || $statusCode === 404) {
            // Сделка удалена или не найдена в amoCRM
            Log::warning("Сделка {$leadId} не найдена в amoCRM (HTTP {$statusCode})");
            return;
        }
        $leadData = json_decode((string) $response->getBody(), true);


        $amoFieldsToUpdate = [];
        $dbLeadData = [
            "amo_lead_id" => $leadData['id'],
            "pipeline_id" => $leadData['pipeline_id'],
            "status_id" => $leadData['status_id'],
            "net_profit" => 0,
            "amo_source_name" => $leadData['_embedded']['source']['name'] ?? null,
            "amo_source_id" => $leadData['_embedded']['source']['id'] ?? null,
            "created_at" => Carbon::createFromTimestamp($lead['date_create'])->toDateTimeString(),
        ];

        
        // Формируем custom fields
        $customFields = [];
        foreach($leadData['custom_fields_values'] ?? [] as $field){
            $customFields[$field['field_id']] = [
                $field['values'][0] ?? null
            ];
        }
        
        // Очищаем метки если сделка скопирована
        $targetFieldSkipped = false;
        $filledCustomFields = [];
        $firstCustomStatusid = array_values(array_values(self::TRACKED_PIPELINES_STATUSES)[0])[0];
        foreach(self::TRACKED_PIPELINES_STATUSES as $statuses){
            foreach($statuses as $statusId => $customId){
                if(!$targetFieldSkipped){
                    if($customId === $firstCustomStatusid){
                        $targetFieldSkipped = true;
                        continue;
                    }
                }
                $dateTime = $customFields[$customId][0] ?? null;
                if($dateTime) $filledCustomFields[] = $customId;
            }
        }
        if($filledCustomFields){
            foreach($filledCustomFields as $customId){
                $amoFieldsToUpdate[] = [
                    'field_id' => $customId,
                    'values'   => null,
                ];
            }
        }   
        // Заполняем $dbLeadData и $amoFieldsToInsert
        $promo_source = [
            "promo_source_name" => $customFields[729721][0]['value'] ?? null,
            "promo_source_enum_id" => (int) ($customFields[729721][0]['enum'] ?? 0)
        ];
        if(!$promo_source["promo_source_enum_id"]){
            $promo_source = $this->leadSourceResolver->get(["Source phone"=>$customFields[410463] ?? null, "Номер Sipuni"=>$customFields[729789] ?? null]);
            if($promo_source) {
                $amoFieldsToUpdate[] = [
                    'field_id' => 729721,
                    'values'   => [
                        ['enum_id' => $promo_source['promo_source_enum_id']],
                    ],
                ];
                $dbLeadData['promo_source_name'] = $promo_source['promo_source_enum_id'];
                $dbLeadData['promo_source_enum_id'] = $promo_source['promo_source_enum_id'];
                $dbLeadData['source_phone'] = $promo_source['source_phone'];
            }
        }else{
            $dbLeadData['promo_source_name'] = $promo_source['promo_source_name'];
            $dbLeadData['promo_source_enum_id'] = $promo_source['promo_source_enum_id'];
            $dbLeadData['source_phone'] = $this->leadSourceResolver->normalizePhoneNum($customFields[410463] ?? null);
        }
        $amoFieldsToUpdate[] = [
            'field_id' => 731973,
            'values' => [
                ['value' => Carbon::createFromTimestamp($lead['date_create'])->timestamp],
            ],
        ];
                
                
        // Патчим сделку
        $response = $client->patch("/api/v4/leads/{$leadId}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'json' => [
                'custom_fields_values' => $amoFieldsToUpdate
            ],
        ]);

        if($response->getStatusCode() === 200){
            $this->leads->updateOrInsert($dbLeadData);
        }
    }
}