<?php
namespace app\Services\amoCRM;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use App\Repositories\TaskRepository;
class TaskFactory {
    protected Client $client;
    private const targetStatuses = [];
    private const targetPipelines = [9088966, 9089018];
    private const TARGET_TASK_STATUSES = [
        // 1. Квалификация v.1
        9088966 => [
            73139654 => ["task_type_id" => 3706410, "execution_time"=> 7200], // 1 Заявка без телефона task Новая заявка 
            73139658 => ["task_type_id" => 3706410, "execution_time"=> 7200], // 1 Ответил
            73139662 => ["task_type_id" => 3706410, "execution_time"=> 7200], // 1 Нет WA
            73140338 => ["task_type_id" => 3706410, "execution_time"=> 7200] // 1 Есть WA
        ],
        // 2. Заказы v.1
        9089018 => [
            73140050 => ["task_type_id" => 3706438, "execution_time"=> 172800], // 2 Квалифицирован task Риск потерять
            73249794 => ["task_type_id" => 3706438, "execution_time"=>432000], // 2 Запрос направлен исполнителям
            73249790 => ["task_type_id" => 3706438, "execution_time"=>10800], // 2 Ставка получена
            73249794 => ["task_type_id" => 3706438, "execution_time"=>1209600], // 2 КП отправлено
            73140058 => ["task_type_id" => 3706438, "execution_time"=>604800], // 2 Данные исполнителю отправлены
            73143094 => ["task_type_id" => 3706438, "execution_time"=> 86400], // 2 24 час до реализации
        ]
    ];
    public function __construct(
        private TaskRepository $tasks
    ){
        $this->client = app('amocrm.client');
    }

    public function execute(int $pipeline_id, int $status_id, int $lead_id){
        $task_type_id = self::TARGET_TASK_STATUSES[$pipeline_id][$status_id]["task_type_id"] ?? null;
        if (!$task_type_id) {
            return;
        }

        
        $taskExist = $this->tasks->insertOrIgnore([$]);
        
        
        $token = config('services.amocrm.token');
        

    }

}