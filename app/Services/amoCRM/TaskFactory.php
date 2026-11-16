<?php
namespace app\Services\amoCRM;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Storage;

class TaskFactory {
    private const targetStatuses = [];
    private const targetPipelines = [];
    private const responsible_user_id = json_decode(Storage::disk('local')->get('amo_duty_manager.json'), true).amo_user_id ?? false;

    public function execute(int $pipeline_id, int $status_id, int $lead_id){
        $task_type_id = null;
        if($pipeline_id === 9088966){
            // Новая заявка
            $task_type_id = 3706410;
        }else{
            // Риск потерять
            $task_type_id = 3706438;
        }

        
    }

}