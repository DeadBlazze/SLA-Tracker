<?php
namespace App\Services\amoCRM;
use Carbon\Carbon; 
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\Log;

class TaskFactory {
    protected Client $client;
    private const targetStatuses = [];
    private const targetPipelines = [9088966, 9089018];
    private const TARGET_TASK_STATUSES = [
        // 1. Квалификация v.1
        9088966 => [
            73139654 => ["task_type_id" => 4421337, "execution_time"=> 7200, "text"=> "Квалифицировать в течение 2 часов"], // 1 Заявка без телефона task Новая заявка 
            73139658 => ["task_type_id" => 4421337, "execution_time"=> 7200, "text"=> "Квалифицировать в течение 2 часов"], // 1 Ответил
            73139662 => ["task_type_id" => 4421337, "execution_time"=> 7200, "text"=> "Квалифицировать в течение 2 часов"], // 1 Нет WA
            73140338 => ["task_type_id" => 4421337, "execution_time"=> 7200, "text"=> "Квалифицировать в течение 2 часов"] // 1 Есть WA
        ],
        // 2. Заказы v.1
        9089018 => [
            73140050 => ["task_type_id" => 4421337, "execution_time"=> 172800, "text"=> "не более 48 часов на этапе"], // 2 Квалифицирован task Риск потерять
            73249786 => ["task_type_id" => 4421337, "execution_time"=>432000, "text"=> "не более 5 дней на этапе"], // 2 Запрос направлен исполнителям
            73249790 => ["task_type_id" => 4421337, "execution_time"=>10800, "text"=> "не более 3 часов на этапе"], // 2 Ставка получена
            73249794 => ["task_type_id" => 4421337, "execution_time"=>1209600, "text"=> "не более 14 дней на этапе"], // 2 КП отправлено
            73140058 => ["task_type_id" => 4421337, "execution_time"=>604800, "text"=> "не более 7 дней на этапе"], // 2 Данные исполнителю отправлены
            73143094 => ["task_type_id" => 4421337, "execution_time"=> 86400, "text"=> "не более 24 часов на этапе"], // 2 24 час до реализации
        ]
    ];
    public function __construct(
        private TaskRepository $tasks
    ){
        $this->client = app('amocrm.client');
    }

    public function execute(int $pipeline_id, int $status_id, int $lead_id, int $responsible_user_id){
        $task_type_id = self::TARGET_TASK_STATUSES[$pipeline_id][$status_id]["task_type_id"] ?? null;
        if (!$task_type_id) {
            return;
        }
        $taskExist = $this->tasks->whereExists(['lead_id' => $lead_id, 'pipeline_id' => $pipeline_id, 'task_type_id' => $task_type_id]);
        if(!$taskExist){
            $nowTimestamp = Carbon::now('UTC')->timestamp;
            $execution_time = self::TARGET_TASK_STATUSES[$pipeline_id][$status_id]["execution_time"];
            $text = self::TARGET_TASK_STATUSES[$pipeline_id][$status_id]["text"];
            $task = [
                'lead_id' => $lead_id,
                'pipeline_id' => $pipeline_id,
                'status_id' => $status_id,
                'task_type_id' => $task_type_id,
                'responsible_user_id' => $responsible_user_id,
                'is_completed' => false,
                'complete_till' => $nowTimestamp + $execution_time,
                'created_at' => Carbon::createFromTimestamp($nowTimestamp)
            ];
            try{
                $token = config('services.amocrm.token');
                $response = $this->client->post("/api/v4/tasks", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                    ],
                    'json' => [
                        [
                            "responsible_user_id" => $task['responsible_user_id'],
                            "entity_type" => "leads",
                            "entity_id" => $lead_id,
                            "text" => $text,
                            "complete_till" => $task["complete_till"],
                            "task_type_id" => $task["task_type_id"]
                        ]
                    ]
                ]);
            } catch (\Throwable $e) {
                Log::error("Ошибка отправки задачи: " . $e->getMessage());
                return;
            }
            $this->tasks->insertOrIgnore($task);
        }
        return;
    }

}