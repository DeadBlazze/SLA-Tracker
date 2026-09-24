<?php
namespace App\Actions\AmoWebhooks;

use Illuminate\Support\Facades\Log;

class ProcessAmoLeadWebhookAction {

    private const ACTION_MAP = [
        'status' => UpdateStatusAction::class,
        'add'    => AddLeadAction::class,
        'delete' => DeleteLeadAction::class,
    ];

    public function handle(array $payload): void
    {
        $leads = $payload['leads'] ?? [];

        if (empty($leads)) {
            Log::warning('AmoCRM webhook: ключ leads пуст или отсутствует', $payload);
            return;
        }

        foreach ($leads as $eventKey => $items) {
            $actionClass = self::ACTION_MAP[$eventKey] ?? null;

            if ($actionClass && is_array($items)) {
                $action = app($actionClass);

                foreach ($items as $leadData) {
                    $action->handle($leadData);
                }
            }
        }    
    }
}