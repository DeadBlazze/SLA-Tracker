<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\Lead;

class LeadRepository{
    public function upsert($data)
    {
        return Lead::upsert(
            $data,                       // 1. Что вставлять (VALUES)
            ['amo_lead_id'],             // 2. Уникальный ключ для проверки дубликата
            array_keys($data)            // 3. Что обновлять в ON DUPLICATE KEY UPDATE
        );
    }
    public function update($data){
        $result = Lead::where("amo_lead_id",$data['amo_lead_id'])->update($data);
        return $result;
    }

}