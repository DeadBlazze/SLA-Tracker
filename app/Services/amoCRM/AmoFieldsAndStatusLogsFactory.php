<?php
namespace app\Services\amoCRM;
use App\Support\AmoPipelines;
use Carbon\Carbon;
class AmoFieldsAndStatusLogsFactory{

    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];

    /**
     * Формирует customFields для AmoCRM и логи истории статусов.
     *
     * @param int $currentPipelineIndex
     * @param int $currentPipelineId
     * @param array $logsCap
     * @param array $flatCustomFields
     * @param Carbon $now
     * @param bool $isRestoration Флаг принудительного сбора данных для восстановления истории
     * @return array{amoFieldsToUpdate: array, dbStatusLogs: array}
     */
    public function get(int $currentPipelineIndex, int $currentPipelineId, array $logsCap, array $flatCustomFields, Carbon $now, bool $isRestoration = false): array {
        $amoFieldsToUpdate = [];
        $dbStatusLogs = [];
        $flatIndex = 0;

        // Флаг, указывающий, что мы перешли к статусам, которые находятся "в будущем"
        $isFutureStage = false;

        foreach (self::PIPELINES_ORDER as $index => $pipelineId) {
            if ($index > $currentPipelineIndex && !$isRestoration) {
                break;
            }

            $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
            $isCurrentPipeline = ($pipelineId === $currentPipelineId);

            foreach ($steps as $stepStatusId => $customFieldId) {
                if ($isFutureStage && $isRestoration) {
                    $amoFieldsToUpdate[] = [
                        'field_id' => $customFieldId,
                        'values'   => null, // Отправляем null, чтобы очистить поле в AmoCRM
                    ];
                    continue;
                }
                $currentDate = $flatCustomFields[$flatIndex]["datetime"] ?? null;

                if (!$currentDate || $isRestoration) {
                    // Ищем ближайшую непустую метку: сначала вперед, если нет — назад
                    $metka = $currentDate;

                    if (!$metka) {
                        for ($i = $flatIndex + 1; $i < count($flatCustomFields); $i++) {
                            if ($flatCustomFields[$i]["datetime"]) {
                                $metka = $flatCustomFields[$i]["datetime"];
                                break;
                            }
                        }

                        if (!$metka) {
                            for ($i = $flatIndex - 1; $i >= 0; $i--) {
                                if ($flatCustomFields[$i]["datetime"]) {
                                    $metka = $flatCustomFields[$i]["datetime"];
                                    break;
                                }
                            }
                        }
                        if (!$metka) $metka = $logsCap['entered_at_iso'];
                    }

                    // Ищем инициатора перехода (user_id) по тому же принципу
                    $syntheticUserId = $flatCustomFields[$flatIndex]["user_id"] ?? null;
                    
                    if (!$syntheticUserId) {
                        for ($i = $flatIndex + 1; $i < count($flatCustomFields); $i++) {
                            if (!empty($flatCustomFields[$i]["user_id"])) {
                                $syntheticUserId = $flatCustomFields[$i]["user_id"];
                                break;
                            }
                        }
                        
                        if (!$syntheticUserId) {
                            for ($i = $flatIndex - 1; $i >= 0; $i--) {
                                if (!empty($flatCustomFields[$i]["user_id"])) {
                                    $syntheticUserId = $flatCustomFields[$i]["user_id"];
                                    break;
                                }
                            }
                        }
                        
                        if (!$syntheticUserId) {
                            $syntheticUserId = $logsCap['responsible_user_id'] ?? null;
                        }
                    }

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
                        'user_id'             => $syntheticUserId,
                        'responsible_user_id' => $logsCap['responsible_user_id'],
                        'entered_at'          => $metka ?? $logsCap['entered_at'],
                        'created_at'          => $now->toDateTimeString(),
                    ];
                }

                // Дошли до текущего статуса — дальше не идем
                if ($isCurrentPipeline && (self::TRACKED_PIPELINES_STATUSES[$pipelineId][$logsCap['status_id']] ?? null) === $customFieldId) {
                    if ($isRestoration) {
                        // Переключаем флаг, чтобы следующие поля занулились
                        $isFutureStage = true; 
                    } else {
                        break 2; 
                    }
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