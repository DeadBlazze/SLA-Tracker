<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'amo_lead_id',
        'pipeline_id',
        'status_id',
        'old_status_id',
        'source_phone',
        'promo_source',
        'promo_source_enum_id',
        'amo_source_id',
        'amo_source_name',
        'net_profit',
        'created_at'
    ];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'bigint';
    public $timestamps = true;
}
