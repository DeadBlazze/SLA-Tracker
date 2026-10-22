<?php
namespace App\Actions\AmoWebhooks;
use Illuminate\Support\Facades\Log;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use App\Services\amoCRM\ResolveLeadSourceService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Support\AmoPipelines;
use App\Services\amoCRM\AmoFieldsAndStatusLogsFactory;
use Illuminate\Support\Facades\DB;

class RestoreStatusLogsActionV2{
    public function __construct(
        private LeadRepository $leads,
        private LeadStatusLogRepository $leadStatusLogs,
        private ResolveLeadSourceService $leadSourceResolver,
        private AmoFieldsAndStatusLogsFactory $amoCustomFieldsAndLogs
    ) {}
    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];

    public function execute($leadId){
        if(!$leadId) return Log::warning('AmoWebhook: Lead payload without valid ID');
        
        // Подгружаем сделку и source
        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);
        try{
            $response = $client->get("/api/v4/leads/{$leadId}?with=source", [
                'headers' => [
                'Authorization' => "Bearer {$token}",
                ],
            ]);
        }catch (RequestException $e) {
            Log::error("Таймаут или сетевая ошибка amoCRM для сделки {$leadId}: " . $e->getMessage());
            throw new \RuntimeException("Ошибка HTTP запроса при получении сделки {$leadId}: " . $e->getMessage(), $e->getCode(), $e);
        }if ($response->getStatusCode() === 204) {
            throw new \RuntimeException("Сделка {$leadId} не найдена или вернула пустой ответ (HTTP 204)");
        }
        
        $leadData = json_decode((string) $response->getBody(), true);

        $currentPipelineId = (int) $leadData['pipeline_id'] ?? 0;
        $currentStatusId = (int) $leadData['status_id'];
        $now = Carbon::now('UTC');

        // Если воронка не наша просто завершаем
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
        if ($currentPipelineIndex === false) {
            return;
        }

        $amoFieldsToUpdate = [];

        $dbLeadData = [
            "amo_lead_id" => $leadData['id'],
            "pipeline_id" => $leadData['pipeline_id'],
            "status_id" => $leadData['status_id'],
            "net_profit" => 0,
            "amo_source_name" => $leadData['_embedded']['source']['name'] ?? null,
            "amo_source_id" => $leadData['_embedded']['source']['id'] ?? null,
            "created_at" => Carbon::createFromTimestamp($leadData['created_at'])->toDateTimeString(),
        ];
        $logsCap = [
            'amo_lead_id'         => $leadData['id'],
            'pipeline_id'         => $currentPipelineId,
            'status_id'           => $currentStatusId,
            'responsible_user_id' => $leadData['responsible_user_id'] ?? null,
            'created_at'          => $now->toDateTimeString()
        ];

        
        // Формируем custom fields
        $customFields = [];
        foreach($leadData['custom_fields_values'] ?? [] as $field){
            $customFields[$field['field_id']] = $field['values'][0] ?? null;
        }
        $dbLeadData['net_profit'] = $customFields[685511]['value'] ?? 0;

        // Заполняем $dbLeadData и $amoFieldsToInsert
        $promo_source = [
            "promo_source_name" => $customFields[729721]['value'] ?? null,
            "promo_source_enum_id" => (int) ($customFields[729721]['enum_id'] ?? 0)
        ];
        if(!$promo_source["promo_source_enum_id"]){
            $promo_source = $this->leadSourceResolver->get(["Source phone"=>$customFields[410463]['value'] ?? null, "Номер Sipuni"=>$customFields[729789]['value'] ?? null]);
            if($promo_source) {
                $amoFieldsToUpdate[729721] = [
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
            $dbLeadData['source_phone'] = $this->leadSourceResolver->normalizePhoneNum($customFields[410463]['value'] ?? null);
        }
        $amoFieldsToUpdate[731973] = [
            'field_id' => 731973,
            'values' => [
                ['value' => Carbon::createFromTimestamp($leadData['created_at'])->toIso8601String()],
            ],
        ];

        $response = $client->get("/api/v4/events?filter[entity]=leads&filter[entity_id]={$leadId}&filter[type]=lead_status_changed", [
            'headers' => [
            'Authorization' => "Bearer {$token}",
            ],
        ]);
        if ($response->getStatusCode() !== 200) {
            Log::error("[HANDLED_ERROR][HTTP_200] AmoWebhook: Failed to fetch lead events {$leadId} from API", [
                'status' => $response->getStatusCode(),
                'body'   => (string) $response->getBody()
            ]);
            return;
        }
        $responseData = json_decode((string) $response->getBody(), true);
        $events = $responseData['_embedded']['events'];
        
        $dbLogsData = [];
        $left_at=null;
        $flatAmoFields=[];
        $highEventCreatedAt = null;
        foreach(($events) as $event){
            $lead_status = $event['value_after'][0]['lead_status'];
            $lead_status_before = $event['value_before'][0]['lead_status'];
            $leftAt = $highEventCreatedAt 
                ? Carbon::createFromTimestamp($highEventCreatedAt)->toDateTimeString() 
                : null;
            $customFieldId = self::TRACKED_PIPELINES_STATUSES[$lead_status['pipeline_id']][$lead_status['id']] ?? null;
            
            if(!$customFieldId) continue;
            $dbLogsData[] = [
                "amo_lead_id"=>$leadId,
                "pipeline_id"=>$lead_status['pipeline_id'],
                "old_status_id"=>$lead_status_before['id'],
                "status_id"=>$lead_status['id'],
                "user_id"=>$event['created_by'],
                "responsible_user_id"=>$logsCap['responsible_user_id'],
                "entered_at" => Carbon::createFromTimestamp($event['created_at'])->toDateTimeString(),
                "created_at"=>$logsCap['created_at'],
                "left_at" => $leftAt
            ];
            $amoFieldsToUpdate[$customFieldId] = [
                'field_id' => $customFieldId,
                'values'   => [
                    ['value' => Carbon::createFromTimestamp($event['created_at'])->toIso8601String()],
                ],
            ];
            $flatAmoFields[$customFieldId] = true;
            
            // Запоминаем время текущего события для следующего (более старого) шага
            $highEventCreatedAt = $event['created_at'];
        }
        array_reverse($dbLogsData);//Переворчиваем логи в ту последовательность в которой должны быть
        foreach(self::PIPELINES_ORDER as $index => $pipelineId){
            $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
            foreach ($steps as $amoStatusId => $customFieldId){
                
                if(empty($flatAmoFields[$customFieldId])){
                    $amoFieldsToUpdate[$customFieldId] = [
                        'field_id' => $customFieldId,
                        'values' => null
                    ];
                }
            }
        }
        
        // Приводим ассоциативный массив к плоскому списку для отправки в API
        $amoPayload = array_values($amoFieldsToUpdate);
        $response = $client->patch("/api/v4/leads/{$leadId}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'json' => [
                'custom_fields_values' => $amoFieldsToUpdate
            ]
        ]);
        if($response->getStatusCode() === 200){
            // insertOrIgnore для предотвращения падения на duplicate entry lead_pipeline_status_unique
            DB::transaction(function () use ($dbLeadData, $dbLogsData) {
                $result0 = $this->leads->upsert($dbLeadData);
                $result = $this->leadStatusLogs->insertOrIgnore($dbLogsData);
            });
        }
        return;
    }
}