<?php
namespace App\Actions\AmoWebhooks;
use Illuminate\Support\Facades\Log;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use App\Services\amoCRM\ResolveLeadSourceService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Support\AmoPipelines;
use App\Services\amoCRM\AmoFieldsAndStatusLogsFactory;
use Illuminate\Support\Facades\DB;

class RestoreStatusLogsAction{
    public function __construct(
        private LeadRepository $leads,
        private LeadStatusLogRepository $leadStatusLogs,
        private ResolveLeadSourceService $leadSourceResolver,
        private AmoFieldsAndStatusLogsFactory $AmoCustomFieldsAndLogs
    ) {}
    private const PIPELINES_ORDER = AmoPipelines::DATA['PIPELINES_ORDER'];
    private const TRACKED_PIPELINES_STATUSES = AmoPipelines::DATA['TRACKED_PIPELINES_STATUSES'];

    public function execute($lead){
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

        $baseDomain = config('services.amocrm.base_domain');
        $token = config('services.amocrm.token');
        $client = new Client([
            'base_uri' => "https://{$baseDomain}",
            'timeout'  => 5.0
        ]);

        $response = $client->get("/api/v4/events?filter[entity]=leads&filter[entity_id]={$lead['id']}&filter[type]=lead_status_changed", [
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
        $responseData = json_decode((string) $response->getBody(), true);
        $events = $responseData['_embedded']['events'];
        usort($events, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        $currentStatusId = array_last($events)['value_after'][0]['lead_status']['id'];
        $currentPipelineId = array_last($events)['value_after'][0]['lead_status']['pipeline_id'];
        $eventsMapByCustomId = [];
        foreach($events as $event){
            $eventsMapByCustomId[$event['value_after'][0]['lead_status']['id']] = [
                $event['created_at']
            ];
        }

        $flatCustomFields = [];
        $i = 0;
        foreach (self::TRACKED_PIPELINES_STATUSES as $pipelineId => $statuses) {
            foreach ($statuses as $statusId => $customId) {
                $timestamp = $eventsMapByCustomId[$statusId][0] ?? null;
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
        $logsCap = [
            'amo_lead_id'         => $events[0]['entity_id'],
            'old_status_id'       => $events[0]['value_before'][0]['lead_status']['id'],
            'status_id'           => $currentStatusId,
            'user_id'             => $events[0]['created_by'],
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'entered_at'          => Carbon::createFromTimestamp($events[0]['created_at'])->toDateTimeString() ?? $now->toDateTimeString(),
            'entered_at_iso'      => Carbon::createFromTimestamp($events[0]['created_at'])->toIso8601String()
        ];
        $dbLeadData = [
            'amo_lead_id'         => $events[0]['entity_id'],
            'status_id'           => $currentStatusId,
            'old_status_id'       => $events[0]['value_before'][0]['lead_status']['id'],
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'pipeline_id' => $currentPipelineId
        ];
        $currentPipelineIndex = array_search($currentPipelineId, self::PIPELINES_ORDER, true);
        $result = $this->AmoCustomFieldsAndLogs->get($currentPipelineIndex, $currentPipelineId, $logsCap, $flatCustomFields, $now);
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
    }
}