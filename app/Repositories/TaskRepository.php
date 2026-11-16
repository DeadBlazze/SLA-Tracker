<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\Task;

class TaskRepository{
    public function upsert($data){
        return Task::upsert(
            $data,                       // 1. Что вставлять (VALUES)
            ['lead_id', 'status_id', 'task_type_id'],             // 2. Уникальный ключ для проверки дубликата
            array_keys($data) 
        );
    }
    public function insertOrIgnore($data){
        return Task::insertOrIgnore($data);
    }
}