<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatusLog extends Model
{
    protected $table = 'lead_status_logs';

    protected $primaryKey = 'id';
    public $incrementing = true;
    
    protected $fillable = [
        'amo_lead_id',
        'pipeline_id',
        'status_id',
        'old_status_id',
        'user_id',
        'responsible_user_id',
        'entered_at',
        'left_at'
    ];
    public $timestamps = true;
    public const UPDATED_AT = null;
}