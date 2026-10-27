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
            ['id']
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
    public function getManagerActivityArchive($userIds, $fromDatetime, $toDatetime){
        $sql = "SELECT * 
            FROM lead_status_logs 
            WHERE entered_at >= ?
            AND entered_at <= ?";
        $params = [$fromDatetime, $toDatetime];

        $usersIdsPlaceholder = implode(',', array_fill(0, count($userIds), '?'));
        $sql.=" AND user_id IN ($usersIdsPlaceholder)
            ORDER BY id ASC";
        foreach($userIds as $userId){
            $params[] = $userId;
        }
        
        $rows = DB::select($sql, $params);
        return $rows;
    }
    public function getLeftTime($amoLeadId, $pipelineId, $statusId){
        return LeadStatusLog::where([
            'amo_lead_id' => $amoLeadId,
            'pipeline_id' => $pipelineId,
            'status_id'   => $statusId,
        ])->latest('entered_at')
        ->value('left_at');
    }
    public function getPreviousDbId($amoLeadId){
        return LeadStatusLog::where(['amo_lead_id' => $amoLeadId])->latest('id')->value('id');
    }
    public function closeLeftTimer($logId, $dateTimeString){
        return LeadStatusLog::where(['id'=>$logId])->update(['left_at'=>$dateTimeString]);
    }
}