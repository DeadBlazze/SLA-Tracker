<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Repositories\LeadRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;

class updateLeadStatusJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $lead
    ){}

    /**
     * Execute the job.
     */
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
    public function handle(): void
    {
        $lead = $this->lead;
        $currentPipelineId = (int) $lead['pipeline_id'] ?? 0;
        $currentStatusId = (int) $lead['status_id'];
        $now = Carbon::now('UTC');

        // Если воронки нет в цепочке пайплайнов — просто пишем в БД и выходим
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);

        if ($currentPipelineIndex === false) {
            // $this->leads->updateStatus($leadId, $currentStatusId, $currentPipelineId);
            return;
        }

        $filledFieldIds = $this->extractFilledCustomFieldIds($lead);
        $fieldsToPatch = [];
        
        // 1. Проверяем все предшествующие воронки и текущую
        foreach (self::PIPELINES_ORDER as $index => $pipelineId) {
            // Воронки, идущие позже текущей, вообще не трогаем
            if ($index > $currentPipelineIndex) {
                break;
            }

            $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
            $isCurrentPipeline = ($pipelineId === $currentPipelineId);

            foreach ($steps as $stepStatusId => $customFieldId) {
                // Если в вебхуке поле не заполнено — закрываем его
                if (! in_array($customFieldId, $filledFieldIds, true)) {
                    $fieldsToPatch[] = [
                        'field_id' => $customFieldId,
                        'values'   => [
                            ['value' => $now->timestamp]
                        ]
                    ];

                    $logsToInsert[] = [
                        'amo_lead_id' => $lead['id'],
                        'pipeline_id' => $pipelineId,
                        'old_status_id' => $lead['old_status_id'] ?? null,
                        'status_id'   => $stepStatusId,
                        'user_id' => $lead['modified_user_id'],
                        "responsible_user_id" => $lead['responsible_user_id'],
                        'entered_at'  => $now->toDateTimeString()
                    ];
                }

                // Если это ТЕКУЩАЯ воронка и мы дошли до текущего статуса — прерываемся
                if ($isCurrentPipeline && $stepStatusId === $currentStatusId) {
                    break;
                }
            }
        }
        
        
        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);

        // Обновляем Дата Время создания
        $client->patch("/api/v4/leads/{$lead['id']}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'json' => [
                'custom_fields_values' => $fieldsToPatch
            ]
        ]);
    }
    private function extractFilledCustomFieldIds(array $lead): array
    {
        $rawFields = $lead['custom_fields'] ?? [];
        $filledIds = [];

        foreach ($rawFields as $field) {
            $fieldId = (int) ($field['id'] ?? 0);
            
            // Проверяем, что значение действительно существует и не пустое
            $hasValue = ! empty($field['values'][0]['value']);

            if ($fieldId > 0 && $hasValue) {
                $filledIds[] = $fieldId;
            }
        }

        return $filledIds;
    }
}
