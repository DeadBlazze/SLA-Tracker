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
     * @return array{amoFieldsToUpdate: array, dbStatusLogs: array}
     */
    public function get(int $currentPipelineIndex, int $currentPipelineId, array $logsCap, array $flatCustomFields, Carbon $now): array {
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