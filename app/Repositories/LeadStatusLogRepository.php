<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\LeadStatusLog;

class LeadStatusLogRepository{
    public function add($data){
        error_log(123);
        $result = LeadStatusLog::create($data);
        return $result;
    }
}