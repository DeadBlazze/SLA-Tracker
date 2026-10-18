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

class RestoreStatusLogsAction{
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
        } catch (RequestException $e) {
            Log::error("Таймаут или сетевая ошибка amoCRM: " . $e->getMessage());
            return;
        }
        if ($response->getStatusCode() === 204) {
            Log::warning("Сделка {$leadId} вернула пустой ответ (HTTP 204 No Content)");
            return;
        }
        $leadData = json_decode((string) $response->getBody(), true);

        $currentPipelineId = (int) $leadData['pipeline_id'] ?? 0;
        $currentStatusId = (int) $leadData['status_id'];
        $oldStatusId = $leadData['old_status_id'] ?? null;
        $now = Carbon::now('UTC');

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
            'old_status_id'       => $oldStatusId,
            'status_id'           => $currentStatusId,
            'user_id'             => $leadData['modified_user_id'] ?? null,
            'responsible_user_id' => $leadData['responsible_user_id'] ?? null,
            'entered_at'          => Carbon::createFromTimestamp($leadData['updated_at'])->toDateTimeString() ?? $now->toDateTimeString(),
            'entered_at_iso'      => Carbon::createFromTimestamp($leadData['updated_at'])->toIso8601String(),
            'created_at'          => $now->toDateTimeString()
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
            $dbLeadData['source_phone'] = $this->leadSourceResolver->normalizePhoneNum($customFields[410463]['value'] ?? null);
        }
        $amoFieldsToUpdate[] = [
            'field_id' => 731973,
            'values' => [
                ['value' => Carbon::createFromTimestamp($leadData['created_at'])->toIso8601String()],
            ],
        ];

        $responseData = json_decode((string) $response->getBody(), true);
        $events = $responseData['_embedded']['events'];
        $eventsMapByAmoId = [];
        foreach(array_reverse($events) as $event){
            $lead_status = $event['value_after'][0]['lead_status'];
            $eventsMapByAmoId[$lead_status['pipeline_id']][$lead_status['id']] 
                = $eventsMapByAmoId[$lead_status['pipeline_id']][$lead_status['id']] ?? ["timestamp"=>$event['created_at'], "user_id"=>$event['created_by']];
        }
        $flatCustomFields = [];
        $i = 0;
        foreach (self::TRACKED_PIPELINES_STATUSES as $pipelineId => $statuses) {
            foreach ($statuses as $statusId => $customId) {
                $timestamp = $eventsMapByAmoId[$pipelineId][$statusId]['timestamp'] ?? null;
                $user_id = $eventsMapByAmoId[$pipelineId][$statusId]['user_id'] ?? null;
                $dateTime = !empty($timestamp) 
                    ? Carbon::createFromTimestamp($timestamp)->toIso8601String() 
                    : null;
                
                $flatCustomFields[] = ["datetime" => $dateTime, "customId" => $customId, "user_id"=>$user_id];
                $i++;
                // Прерываем оба цикла, когда дошли до текущей точки сделки
                if ($pipelineId == $currentPipelineId && $statusId == $currentStatusId) {
                    break 2;
                }
            }
        }
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
        $result = $this->amoCustomFieldsAndLogs->get($currentPipelineIndex, $currentPipelineId, $logsCap, $flatCustomFields, $now, true);
        array_push($amoFieldsToUpdate, ...$result['amoFieldsToUpdate']);
        $dbStatusLogs = $result['dbStatusLogs'];
        
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
            DB::transaction(function () use ($dbLeadData, $dbStatusLogs) {
                $result0 = $this->leads->upsert($dbLeadData);
                $result = $this->leadStatusLogs->insertOrIgnore($dbStatusLogs);
            });
        }
        return;
    }
}