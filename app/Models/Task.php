<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    const CREATED_AT = null;

    public $incrementing = false;
    protected $primaryKey = 'task_id';
    protected $keyType = 'int';

    protected $fillable = [
        'lead_id',
        'pipeline_id',
        'status_id',
        'task_type_id',
        'task_id',
        'responsible_user_id',
        'is_completed',
        'complete_till',
        'created_at',
    ];
}
