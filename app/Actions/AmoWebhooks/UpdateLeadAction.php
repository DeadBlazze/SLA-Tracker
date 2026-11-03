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

        $leadModel = Lead::where('amo_lead_id', $leadId)->first();

        // Если сделки физически нет в базе — восстанавливаем логи / создаем
        if (!$leadModel) {
            $this->restoreStatusLogs->execute($leadId);
            return;
        }

        // Формируем custom fields
        $customFields = [];
        foreach($lead['custom_fields'] ?? [] as $field){
            $customFields[$field['id']] = $field['values'][0] ?? null;
        }

        $loadedAt = $customFields[726049]['value'] ?? null;
        // Заполняем модель новыми данными
        $leadModel->fill([
            'status_id'           => $lead['status_id'],
            'old_status_id'       => $lead['old_status_id'] ?? null,
            'responsible_user_id' => $lead['responsible_user_id'] ?? null,
            'pipeline_id'         => $lead['pipeline_id'],
            'net_profit'          => $customFields[685511] ?? 0,
            'cargo_loaded_at'     => $loadedAt ? Carbon::createFromTimestamp($loadedAt)->toDateTimeString() : null,
        ]);

        // UPDATE выполнится только если хотя бы одно значение реально отличается от того, что в БД
        if ($leadModel->isDirty()) {
            $leadModel->save();
        }
    }
}