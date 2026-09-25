<?php
namespace App\Actions\AmoWebhooks;

use App\Repositories\LeadRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UpdateStatusAction{
    public function __construct(
        private LeadRepository $leads
    ) {}
    private const TRACKED_PIPELINES_STATUSES = [
        // Слева amo_source_id справа custom_field_id
        [
            "id" => 9088966,
            "name" => "Квалификация v.1",
            "statuses" => [
                [
                    "id"=> 73140342,
                    "pipeline_id"=> 9088966,
                    "target_custom_id" => 
                    "name"=> "Взяли в работу"
                ],
                [
                    "id"=> 143,
                    "pipeline_id"=> 9088966,
                    "name"=> "Закрыто и не реализовано",
                ]
            ]
        ],
        [
            "id"=> 9089018,
            "name"=> "Заказы v.1",
            "statuses" => [ 
                [
                    "id"=> 73140050,
                    "name"=> "Квалифицирован",
                    "pipeline_id"=> 9089018
                ],
                [
                    "id"=> 73249794,
                    "name"=> "КП отправлено",
                    "pipeline_id" => 9089018
                ],
                [
                    "id"=> 73140058,
                    "name"=> "Данные исполнителю отправлены",
                ],
                [
                    "id"=> 73143094,
                    "name"=> "24 час до реализации",
                ],
                [
                    "id"=> 73143098,
                    "name"=> "Начали реализацию"
                ],
                [
                    "id"=> 73143102,
                    "name"=> "Закончили реализацию",
                ],
                [
                    "id"=> 142,
                    "name"=> "Успешно реализовано",
                ],
                [
                    "id"=> 143,
                    "name"=> "Закрыто и не реализовано",
                ]
            ]
        ]
    ];
    public function handle($lead){
        $leadId = (int) $lead['id'];
        $currentStatusId = (int) $lead['status_id'];
        $pipelineId = (int) ($lead['pipeline_id'] ?? 0);
        $now = Carbon::now('UTC');
        $timestamp = $now->timestamp;
        $filledFieldIds = $this->extractFilledCustomFieldIds($lead);
        
        // 2. Проходим цепочку шагов от первого до текущего статуса
        foreach (self::PIPELINE_STEPS as $stepStatusId => $targetFieldId) {
            if (! in_array($targetFieldId, $filledFieldIds, true)) {
                $fieldsToPatch[] = [
                    'field_id' => $targetFieldId,
                    'values'   => [
                        ['value' => $timestamp]
                    ]
                ];
            }
            if($stepStatusId === $lead['status_id']) break;
        }
        // $fieldId = self::STATUS_FIELD_MAP[$lead['status_id']] ?? null;
        // if ($fieldId === null) {
        //     Log::warning('amoCRM webhook: статус не найден в STATUS_FIELD_MAP', [
        //         'lead_id'     => $lead['id'],
        //         'status_id'   => $lead['status_id'],
        //         'pipeline_id' => $lead['pipeline_id'] ?? null,
        //     ]);

        //     return;
        // }
        
        // $dt = Carbon::now();

        // $baseDomain = config('services.amocrm.base_domain');
        // $token = config('services.amocrm.token');
        // $client = new Client([
        //     'base_uri' => "https://{$baseDomain}",
        //     'timeout'  => 5.0
        // ]);

        // // Обновляем Дата Время создания
        // $client->patch("/api/v4/leads/{$lead['id']}", [
        //     'headers' => [
        //         'Authorization' => "Bearer {$token}",
        //     ],
        //     'json' => [
        //         'custom_fields_values' => [
        //             [
        //                 'field_id' => $lead[''],
        //                 'values' => [
        //                     ['value' => $timestamp],
        //                 ],
        //             ],
        //         ],
        //     ],
        // ]);
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