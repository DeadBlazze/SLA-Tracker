<?php

foreach (self::PIPELINES_ORDER as $index => $pipelineId) {
            // Воронки, идущие позже текущей, вообще не трогаем
            if ($index > $currentPipelineIndex) {
                break;
            }
            
            $steps = self::TRACKED_PIPELINES_STATUSES[$pipelineId] ?? [];
            $isCurrentPipeline = ($pipelineId === $currentPipelineId);

            foreach ($steps as $stepStatusId => $customFieldId) {
                // Дошли до текущего статуса — дальше не идем (текущий уже обработан выше)
                if ($isCurrentPipeline && $stepStatusId === $currentStatusId) {
                    break;
                }    
                // Проверяем только пропущенные шаги
                if (! in_array($customFieldId, $filledFieldIds, true)) {
                    $amoFieldsToUpdate[] = [
                        'field_id' => $customFieldId,
                        'values'   => [
                            ['value' => $now->timestamp],
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
                        'created_at'          => $now->toDateTimeString()
                    ];
                }
            }
        }