<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\LeadStatusLog;

class LeadStatusLogRepository{
    public function add($data){
        $result = null;
        foreach($data as $logData){
            $result[] = LeadStatusLog::create($logData);
        }
        return $result;
    }
    public function upsert($data){
        LeadStatusLog::upsert($data);
    }
    public function insertOrIgnore($data){
        LeadStatusLog::insertOrIgnore($data);
    }
}