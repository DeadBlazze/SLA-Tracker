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
    public function insert($data){
        LeadStatusLog::insert($data);
    }
    public function upsertByUniqueIndex($data){
        LeadStatusLog::upsert(
            $data,
            ['lead_pipeline_status_unique'],
            ['id' => DB::raw('id')]
        );
    }
    public function insertOrIgnore($data){
        LeadStatusLog::insertOrIgnore($data);
    }
    public function getManagerActivity($userIds, $timestampFrom, $timestampTo){
        return LeadStatusLog::query()
        ->whereBetween('entered_at', [$timestampFrom, $timestampTo])
        ->whereIn('user_id', $userIds)
        ->orderBy('id', 'asc')
        ->get();
    }
    public function getManagerActivityArchive($userIds, $timestampFrom, $timestampTo){
        $sql = "SELECT * 
            FROM lead_status_logs 
            WHERE entered_at >= ?
            AND entered_at <= ?";
        $params = [$timestampFrom, $timestampTo];

        $usersIdsPlaceholder = implode(',', array_fill(0, count($userIds), '?'));
        $sql.=" AND user_id IN ($usersIdsPlaceholder)
            ORDER BY id ASC";
        foreach($userIds as $userId){
            $params[] = $userId;
        }
        
        $rows = DB::select($sql, $params);
        return $rows;
    }
}