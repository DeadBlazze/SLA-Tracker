<?php
namespace App\Actions\AmoWebhooks;

use Illuminate\Support\Facades\Log;
use App\Repositories\LeadRepository;
use Carbon\Carbon;
use App\Actions\AmoWebhooks\RestoreStatusLogsActionV2;
use App\Models\Lead;

class UpdateLeadAction {
     public function __construct(
        private LeadRepository $leads,
        private RestoreStatusLogsActionV2 $restoreStatusLogs
    ) {}
    public function handle($lead){
        $leadId = !empty($lead['id']) ? (int) $lead['id'] : null;
        if(!$leadId) return Log::warning('AmoWebhook: Lead payload without valid ID', ['payload' => $lead]);

        $dbLeadData = [
            "amo_lead_id" => $lead['id'],
            "status_id" => $lead['status_id'],
            "old_status_id" => $lead['old_status_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'pipeline_id'         => $lead['pipeline_id']
        ];

        // Формируем custom fields
        $customFields = [];
        foreach($lead['custom_fields'] ?? [] as $field){
            $customFields[$field['id']] = [
                $field['values'][0] ?? null
            ];
        }

        $dbLeadData['net_profit'] = $customFields[685511][0]['value'] ?? 0;
        $loaded_at = $customFields[726049][0]['value'] ?? null;
        if($loaded_at) $dbLeadData['cargo_loaded_at'] =  Carbon::createFromTimestamp($loaded_at)->toDateTimeString();

        $affected = Lead::where('amo_lead_id', $leadId)->update($dbLeadData);

        if ($affected === 0) {
            $exists = Lead::where('amo_lead_id', $leadId)->exists();
            
            // Сделки действительно нет (не создавалась или была удалена)
            if (!$exists) {
                $this->restoreStatusLogs->execute($leadId);
            }
        }
    }
}