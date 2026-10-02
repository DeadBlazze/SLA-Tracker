<?php
namespace App\Actions\AmoWebhooks;

use Illuminate\Support\Facades\Log;
use App\Jobs\updateLeadStatusJob;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Services\amoCRM\ResolveLeadSourceService;
use Illuminate\Support\Facades\DB;


class UpdateStatusAction{
    public function __construct(
        private LeadRepository $leads,
        private LeadStatusLogRepository $leadStatusLogs,
        private ResolveLeadSourceService $leadSourceResolver
    ) {}
    private const PIPELINES_ORDER = [
        9088966, // 1. Сначала идет «Квалификация v.1»
        9089018, // 2. Затем «Заказы v.1»
    ];

    private const TRACKED_PIPELINES_STATUSES = [
        // 1. Квалификация v.1
        9088966 => [
            73140342 => 732013, // Взяли в работу
        ],

        // 2. Заказы v.1
        9089018 => [
            73140050 => 731999, // Квалифицирован
            73249794 => 731997, // КП отправлено
            73140058 => 732001, // Данные исполнителю отправлены
            73143094 => 732003, // 24 час до реализации
            73143098 => 732005, // Начали реализацию
            73143102 => 732007, // Закончили реализацию
            142      => 732009, // Успешно реализовано
            143      => 732011, // Закрыто и не реализовано
        ]
    ]; 
    
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
        $dbStatusLogs = [[
            'amo_lead_id'         => $lead['id'],
            'pipeline_id'         => $currentPipelineId,
            'old_status_id'       => $oldStatusId,
            'status_id'           => $currentStatusId,
            'user_id'             => $lead['modified_user_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'entered_at'          => Carbon::createFromTimestamp($lead['updated_at'])->toDateTimeString() ?? $now->toDateTimeString(),
            'created_at'          => $now->toDateTimeString()
        ]];


        // Data for DB
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


        // Если воронки нет в цепочке пайплайнов — просто пишем в БД и выходим
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
        if ($currentPipelineIndex === false) {
            $this->leads->update($dbLeadData);
            error_log(123);
            return;
        }


        $filledFieldIds = $this->extractFilledCustomFieldIds($lead);
        

        // Не было перехода по status_id => update DB + return
        $statusChanged = ($oldStatusId !== $currentStatusId);
        if (!$statusChanged){
            $this->leads->update($dbLeadData);
            return;
        }

        // НАВЕРСТЫВАНИЕ ПРОПУЩЕННЫХ ШАГОВ
        $flatCustomFields = [];
        foreach(self::TRACKED_PIPELINES_STATUSES as $statuses){
            foreach($statuses as $key => $value){
                $dateTime = !empty($customFields[$value][0]) ? Carbon::createFromTimestamp($customFields[$value][0])->toIso8601String() : null;
                $flatCustomFields[] = [$dateTime, $value];
            }
        }

        $timestampGuide = [];
        $flatIndex = 0;
        
        foreach (self::PIPELINES_ORDER as $index => $pipelineId) {
            // Воронки, идущие позже текущей, вообще не трогаем
            if ($index > $currentPipelineIndex) {
                break;
            }

            $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
            $isCurrentPipeline = ($pipelineId === $currentPipelineId);

            foreach ($steps as $stepStatusId => $customFieldId) {
                if(!$flatCustomFields[$flatIndex][0]){
                    $metka = null;
                    $lookupForward = array_slice($flatCustomFields, $flatIndex+1);
                    $lookupEmpty = true;
                    foreach($lookupForward as $array){
                        if($array[0]) $lookupEmpty = false;
                        break;
                    }
                    if(!$lookupEmpty){
                        foreach($lookupForward as $array){
                            if($array[0]){
                                $metka = $array[0];
                                break;
                            }
                        }
                    }else{
                        for($i = $flatIndex-1; $i >= 0; $i--){
                            if($flatCustomFields[$i][0]){
                                $metka = $flatCustomFields[$i][0];
                                break;
                            }
                        }
                    }
                    $amoFieldsToUpdate[] = [
                        'field_id' => $customFieldId,
                        'values'   => [
                            ['value' => $metka],
                        ]
                    ];
                    $dbStatusLogs[] = [
                        'amo_lead_id'         => $lead['id'],
                        'pipeline_id'         => $pipelineId,
                        'old_status_id'       => $oldStatusId,
                        'status_id'           => $stepStatusId,
                        'user_id'             => $lead['modified_user_id'] ?? null,
                        'responsible_user_id' => $lead['responsible_user_id'] ?? null,
                        'entered_at'          => Carbon::createFromTimestamp($lead['updated_at'] ?? $now->timestamp)->toDateTimeString(),
                        'created_at'          => $now->toDateTimeString()
                    ];
                }else{
                    $amoFieldsToUpdate[] = [
                        'field_id' => $customFieldId,
                        'values'   => [
                            ['value' => $flatCustomFields[$flatIndex][0]],
                        ]
                    ];
                }
                // Дошли до текущего статуса — дальше не идем (текущий уже обработан выше)
                if ($isCurrentPipeline && self::TRACKED_PIPELINES_STATUSES[$pipelineId][$lead['status_id']] === $customFieldId) {
                    error_log(123);
                    break;
                }
                $flatIndex++;
            }
        }
        
        
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
                    $result = $this->leadStatusLogs->add($dbStatusLogs);
                    $result0 = $this->leads->update($dbLeadData);
                });
            }catch(\Illuminate\Database\QueryException $e){
                if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1452) {
                    // Сделки нет в БД -> идём в amoCRM API за полными данными
                    $response = $client->get("/api/v4/leads/{$leadId}?with=source", [
                        'headers' => [
                        'Authorization' => "Bearer {$token}",
                        ],
                    ]);
                    if ($response->getStatusCode() !== 200) {
                        Log::error("[HANDLED_ERROR][HTTP_200] AmoWebhook: Failed to fetch lead {$leadId} from API", [
                            'status' => $response->getStatusCode(),
                            'body'   => (string) $response->getBody()
                        ]);
                        return;
                    }
                    $responseData = json_decode((string) $response->getBody(), true);;
                    $fullLeadData = array_merge($dbLeadData, [
                        "amo_source_name" => $responseData['_embedded']['source']['name'] ?? null,
                        "amo_source_id" => $responseData['_embedded']['source']['id'] ?? null,
                        "created_at" => Carbon::createFromTimestamp($responseData['created_at'])->toDateTimeString(),
                    ]);
                    DB::transaction(function () use ($fullLeadData, $dbStatusLogs) {
                        // Создаем родительскую сделку
                        $this->leads->add($fullLeadData);
                        $this->leadStatusLogs->add($dbStatusLogs);
                    });
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