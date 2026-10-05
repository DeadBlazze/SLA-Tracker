<?php
/**
 * Формирует поля для обновления AmoCRM и логи истории статусов.
 *
 * @param array $lead
 * @param int $currentPipelineIndex
 * @param int $currentPipelineId
 * @param int|null $oldStatusId
 * @param array $customFields
 * @param Carbon $now
 * @return array{amoFieldsToUpdate: array, dbStatusLogs: array}
 */
private function getStatusLogsAndAmoFields(
    array $lead,
    int $currentPipelineIndex,
    int $currentPipelineId,
    ?int $oldStatusId,
    array $customFields,
    Carbon $now
): array {
    $flatCustomFields = [];
    foreach (self::TRACKED_PIPELINES_STATUSES as $statuses) {
        foreach ($statuses as $value) {
            $dateTime = !empty($customFields[$value][0])
                ? Carbon::createFromTimestamp($customFields[$value][0])->toIso8601String()
                : null;
            $flatCustomFields[] = [$dateTime, $value];
        }
    }

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

                $amoFieldsToUpdate[] = [
                    'field_id' => $customFieldId,
                    'values'   => [
                        ['value' => $metka],
                    ],
                ];

                $dbStatusLogs[] = [
                    'amo_lead_id'         => $lead['id'],
                    'pipeline_id'         => $pipelineId,
                    'old_status_id'       => $oldStatusId,
                    'status_id'           => $stepStatusId,
                    'user_id'             => $lead['modified_user_id'] ?? null,
                    'responsible_user_id' => $lead['responsible_user_id'] ?? null,
                    'entered_at'          => Carbon::createFromTimestamp($lead['updated_at'] ?? $now->timestamp)->toDateTimeString(),
                    'created_at'          => $now->toDateTimeString(),
                ];
            } else {
                $amoFieldsToUpdate[] = [
                    'field_id' => $customFieldId,
                    'values'   => [
                        ['value' => $currentDate],
                    ],
                ];
            }

            // Дошли до текущего статуса — дальше не идем
            if ($isCurrentPipeline && (self::TRACKED_PIPELINES_STATUSES[$pipelineId][$lead['status_id']] ?? null) === $customFieldId) {
                break;
            }

            $flatIndex++;
        }
    }

    return [
        'amoFieldsToUpdate' => $amoFieldsToUpdate,
        'dbStatusLogs'      => $dbStatusLogs,
    ];
}