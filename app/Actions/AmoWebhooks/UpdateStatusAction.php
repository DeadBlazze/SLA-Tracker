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

class UpdateStatusAction{
    public function __construct(
        private LeadRepository $leads,
        private LeadStatusLogRepository $leadStatusLogs,
        private ResolveLeadSourceService $leadSourceResolver
    ) {}

    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];
    
    public function handle($lead){
        $leadId = !empty($lead['id']) ? (int) $lead['id'] : null;
        if(!$leadId) return Log::warning('AmoWebhook: Lead payload without valid ID', ['payload' => $lead]);

        $currentPipelineId = (int) $lead['pipeline_id'] ?? 0;
        $currentStatusId = (int) $lead['status_id'];
        $oldStatusId = $lead['old_status_id'] ?? null;
        $now = Carbon::now('UTC');
        
        $amoFieldsToUpdate = [];
        $dbLeadData = [
            "amo_lead_id" => $lead['id'],
            "status_id" => $lead['status_id'],
            "old_status_id" => $lead['old_status_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'pipeline_id'         => $currentPipelineId
        ];
        $dbStatusLogs = [];
        $logsCap = [
            'amo_lead_id'         => $lead['id'],
            'pipeline_id'         => $currentPipelineId,
            'old_status_id'       => $oldStatusId,
            'status_id'           => $currentStatusId,
            'user_id'             => $lead['modified_user_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'entered_at'          => Carbon::createFromTimestamp($lead['updated_at'])->toDateTimeString() ?? $now->toDateTimeString(),
            'entered_at_iso'      => Carbon::createFromTimestamp($lead['updated_at'])->toIso8601String(),
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


        // Если воронки или статуса нет в цепочке пайплайнов — просто пишем в БД и выходим
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
        if ($currentPipelineIndex === false) {
            $this->leads->update($dbLeadData);
            error_log(123);
            return;
        }
  

        // Не было перехода по status_id => update DB + return
        $statusChanged = ($oldStatusId !== $currentStatusId);
        if (!$statusChanged){
            $this->leads->update($dbLeadData);
            return;
        }

        
        // НАВЕРСТЫВАНИЕ ПРОПУЩЕННЫХ ШАГОВ
        $flatCustomFields = [];
        foreach(self::TRACKED_PIPELINES_STATUSES as $statuses){
            foreach($statuses as $key => $customId){
                $dateTime = !empty($customFields[$customId][0]) ? Carbon::createFromTimestamp($customFields[$customId][0])->toIso8601String() : null;
                $flatCustomFields[] = [$dateTime, $customId];
            }
        }
        $resultArray = $this->getStatusLogsAndAmoFields($currentPipelineIndex, $currentPipelineId, $logsCap, $flatCustomFields, $now);
        array_push($amoFieldsToUpdate, ...$resultArray['amoFieldsToUpdate']);
        array_push($dbStatusLogs, ...$resultArray['dbStatusLogs']);
        
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

        // Пишем в базу
        if($response->getStatusCode() === 200){
            // Обработка ошибки добавления лога по внешнему ключу на amo_lead_id
            try{
                DB::transaction(function () use ($dbLeadData, $dbStatusLogs) {
                    $this->leadStatusLogs->upsertByUniqueIndex($dbStatusLogs);
                    $this->leads->update($dbLeadData);
                });
            }catch(\Illuminate\Database\QueryException $e){
                if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1452) {
                    // Подгружаем сделку и source
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
                    $dbStatusLogs = [];
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
                        $customFields[$field['field_id']] = [
                            $field['values'][0] ?? null
                        ];
                    }
                    $dbLeadData['net_profit'] = $customFields[685511][0]['value'] ?? 0;
                    
                    // Очищаем метки если сделка скопирована
                    $filledCustomFields = [];
                    foreach(self::TRACKED_PIPELINES_STATUSES as $statuses){
                        foreach($statuses as $statusId => $customId){
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
                            ['value' => Carbon::createFromTimestamp($lead['date_create'])->toIso8601String()],
                        ],
                    ];

                    $responseData = json_decode((string) $response->getBody(), true);
                    $events = $responseData['_embedded']['events'];
                    // usort($events, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
                    // $currentStatusId = array_last($events)['value_after'][0]['lead_status']['id'];
                    // $currentPipelineId = array_last($events)['value_after'][0]['lead_status']['pipeline_id'];
                    $eventsMapByAmoId = [];
                    foreach(array_reverse($events) as $event){
                        $lead_status = $event['value_after'][0]['lead_status'];
                        $eventsMapByAmoId[$lead_status['pipeline_id']][$lead_status['id']] 
                            = $eventsMapByAmoId[$lead_status['pipeline_id']][$lead_status['id']] ?? $event['created_at'];
                    }

                    $flatCustomFields = [];
                    $i = 0;
                    foreach (self::TRACKED_PIPELINES_STATUSES as $pipelineId => $statuses) {
                        foreach ($statuses as $statusId => $customId) {
                            $timestamp = $eventsMapByAmoId[$pipelineId][$statusId] ?? null;
                            $dateTime = !empty($timestamp) 
                                ? Carbon::createFromTimestamp($timestamp)->toIso8601String() 
                                : null;

                            $flatCustomFields[] = [$dateTime, $customId];
                            $i++;
                            // Прерываем оба цикла, когда дошли до текущей точки сделки
                            if ($pipelineId == $currentPipelineId && $statusId == $currentStatusId) {
                                break 2;
                            }
                        }
                    }
                    $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
                    $result = $this->getStatusLogsAndAmoFields($currentPipelineIndex, $currentPipelineId, $logsCap, $flatCustomFields, $now);
                    $amoFieldsToUpdate = $result['amoFieldsToUpdate'];
                    $dbStatusLogs      = $result['dbStatusLogs'];
                    $response = $client->patch("/api/v4/leads/{$lead['id']}", [
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
                        error_log(123);
                    }
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
    /**
 * Формирует поля для обновления AmoCRM и логи истории статусов.
 *
 * @param int $currentPipelineIndex
 * @param int $currentPipelineId
 * @param array $logsCap
 * @param array $flatCustomFields
 * @param Carbon $now
 * @return array{amoFieldsToUpdate: array, dbStatusLogs: array}
 */
private function getStatusLogsAndAmoFields(
    int $currentPipelineIndex,
    int $currentPipelineId,
    array $logsCap,
    array $flatCustomFields,
    Carbon $now
): array {
    $amoFieldsToUpdate = [];
    $dbStatusLogs = [];
    $flatIndex = 0;

    foreach (self::PIPELINES_ORDER as $index => $pipelineId) {
        if ($index > $currentPipelineIndex) {
            break;
        }

        $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
        $isCurrentPipeline = ($pipelineId === $currentPipelineId);

        foreach ($steps as $stepStatusId => $customFieldId) {
            $currentDate = $flatCustomFields[$flatIndex][0] ?? null;

            if (!$currentDate) {
                // Ищем ближайшую непустую метку: сначала вперед, если нет — назад
                $metka = null;

                for ($i = $flatIndex + 1; $i < count($flatCustomFields); $i++) {
                    if ($flatCustomFields[$i][0]) {
                        $metka = $flatCustomFields[$i][0];
                        break;
                    }
                }

                if (!$metka) {
                    for ($i = $flatIndex - 1; $i >= 0; $i--) {
                        if ($flatCustomFields[$i][0]) {
                            $metka = $flatCustomFields[$i][0];
                            break;
                        }
                    }
                }
                if(!$metka) $metka = $logsCap['entered_at_iso'];
                $amoFieldsToUpdate[] = [
                    'field_id' => $customFieldId,
                    'values'   => [
                        ['value' => $metka],
                    ],
                ];

                $dbStatusLogs[] = [
                    'amo_lead_id'         => $logsCap['amo_lead_id'],
                    'pipeline_id'         => $pipelineId,
                    'old_status_id'       => $logsCap['old_status_id'],
                    'status_id'           => $stepStatusId,
                    'user_id'             => $logsCap['user_id'],
                    'responsible_user_id' => $logsCap['responsible_user_id'],
                    'entered_at'          => $logsCap['entered_at'],
                    'created_at'          => $now->toDateTimeString(),
                ];
            }

            // Дошли до текущего статуса — дальше не идем
            if ($isCurrentPipeline && (self::TRACKED_PIPELINES_STATUSES[$pipelineId][$logsCap['status_id']] ?? null) === $customFieldId) {
                break 2;
            }

            $flatIndex++;
        }
    }

    return [
        'amoFieldsToUpdate' => $amoFieldsToUpdate,
        'dbStatusLogs'      => $dbStatusLogs,
    ];
}
}