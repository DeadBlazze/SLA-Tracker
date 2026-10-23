<?php
namespace App\Actions\AmoWebhooks;

use Illuminate\Support\Facades\Log;
use App\Jobs\updateLeadStatusJob;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Services\amoCRM\ResolveLeadSourceService;
use Illuminate\Support\Facades\DB;
use App\Support\AmoPipelines;
use App\Actions\AmoWebhooks\RestoreStatusLogsActionV2;
use App\Services\amoCRM\AmoFieldsAndStatusLogsFactory;
use Illuminate\Support\Facades\File;
class UpdateStatusActionV2{
    public function __construct(
        private LeadRepository $leads,
        private LeadStatusLogRepository $leadStatusLogs,
        private ResolveLeadSourceService $leadSourceResolver,
        private RestoreStatusLogsActionV2 $restoreStatusLogs,
        private AmoFieldsAndStatusLogsFactory $amoCustomFieldsAndLogs
    ) {}

    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];
    
    public function handle($lead){
        $leadId = !empty($lead['id']) ? (int) $lead['id'] : null;
        if(!$leadId) return Log::warning('AmoWebhook: Lead payload without valid ID', ['payload' => $lead]);
        $now = Carbon::now('UTC');

        // Лог запросов updateLead
        $datetime = now()->toDateTimeString();
        $logLeadData = json_encode($lead, JSON_UNESCAPED_UNICODE);
        $logLine = "[$datetime] $logLeadData" . PHP_EOL;
        $path = storage_path('logs/UpdateAmoRequests.log');
        File::append($path, $logLine);

        $currentPipelineId = (int) $lead['pipeline_id'] ?? 0;
        $currentStatusId = (int) $lead['status_id'];
        $oldStatusId = $lead['old_status_id'] ?? null;
        
        $amoFieldsToUpdate = [];

        $dbLeadData = [
            "amo_lead_id" => $lead['id'],
            "status_id" => $lead['status_id'],
            "old_status_id" => $lead['old_status_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'pipeline_id'         => $currentPipelineId
        ];
        $dbLogData = [
            'amo_lead_id'         => $lead['id'],
            'pipeline_id'         => $currentPipelineId,
            'old_status_id'       => $oldStatusId,
            'status_id'           => $currentStatusId,
            'user_id'             => $lead['modified_user_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'entered_at'          => Carbon::createFromTimestamp($lead['updated_at'])->toDateTimeString(),
            'created_at'          => $now->toDateTimeString()
        ];

        // Формируем custom fields
        $customFields = [];
        foreach($lead['custom_fields'] ?? [] as $field){
            $customFields[$field['id']] = [
                $field['values'][0] ?? null
            ];
        }
        $dbLeadData['net_profit'] = $customFields[685511][0]['value'] ?? 0;

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
            $dbLeadData['source_phone'] = $this->leadSourceResolver->normalizePhoneNum($customFields[410463][0]['value'] ?? null);
        }

        
        // Если статус нецелевой — пишем в БД и выходим
        $customFieldId = self::TRACKED_PIPELINES_STATUSES[$currentPipelineId][$currentStatusId] ?? null;
        if (!$customFieldId) {
            $this->leads->update($dbLeadData);
            return;
        }
        $amoFieldsToUpdate[] = [
            'field_id' => $customFieldId,
            'values' => [
                ['value' => Carbon::createFromTimestamp($lead['updated_at'])->toIso8601String()],
            ],
        ];

        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);

        // Patch amo Дата Время создания
        $response = $client->patch("/api/v4/leads/{$lead['id']}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'json' => [
                'custom_fields_values' => $amoFieldsToUpdate
            ]
        ]);
        
        $lastStatusDbId = $this->leadStatusLogs->getPreviousDbId($leadId);
        // Пишем в базу
        if($response->getStatusCode() === 200){
            // Обработка ошибки добавления лога по внешнему ключу на amo_lead_id
            try{
                DB::transaction(function () use ($dbLeadData, $dbLogData, $lastStatusDbId, $datetime) {
                    if($lastStatusDbId) $this->leadStatusLogs->closeLeftTimer($lastStatusDbId,$datetime);
                    $this->leadStatusLogs->upsertByUniqueIndex($dbLogData);
                    $this->leads->update($dbLeadData);
                });
            }catch(\Illuminate\Database\QueryException $e){
                if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1452) {
                    // Подгружаем сделку и source
                    $this->restoreStatusLogs->execute($leadId);
                    return;
                }else {
                    // Любая другая ошибка базы должна валиться дальше
                    throw $e;
                }
            }
        }
        // updateLeadStatusJob::dispatch($lead);
    }
    private function extractFilledCustomFieldIds(array $lead): array
    {
        $rawFields = $lead['custom_fields'] ?? [];
        $filledIds = [];

        foreach ($rawFields as $field) {
            $fieldId = (int) ($field['id'] ?? 0);
            
            // Проверяем, что значение действительно существует и не пустое
            $hasValue = ! empty($field['values'][0]);

            if ($fieldId > 0 && $hasValue) {
                $filledIds[] = $fieldId;
            }
        }

        return $filledIds;
    }
}