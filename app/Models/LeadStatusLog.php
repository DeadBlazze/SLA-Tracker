<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatusLog extends Model
{
    protected $table = 'lead_status_logs';

    protected $fillable = [
        'lead_id',
        'pipeline_id',
        'status_id',
        'old_status_id',
        'user_id',
        'responsible_user_id',
        'entered_at'
    ];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'bigint';
    public $timestamps = true;
}