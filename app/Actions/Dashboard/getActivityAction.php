<?php
namespace App\Actions\Dashboard;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use App\Support\AmoUsers;
use App\Support\AmoPipelines;
use Carbon\Carbon;
class getActivityAction{
    public function __construct(
        public LeadRepository $leads,
        public LeadStatusLogRepository $leadLogs
    ){}
    private const USERS = AmoUsers::DATA['USERS'];
    private const USERS_LABELS = AmoUsers::DATA['USERS_LABELS'];
    private const STATUSES_LABELS = AmoPipelines::DATA['STATUSES_LABELS'];
    public function execute($data){
        $timestampFrom = Carbon::createFromTimestamp($data['timestampFrom'])->toDateTimeString();
        $timestampTo = Carbon::createFromTimestamp($data['timestampTo'])->toDateTimeString();
        $groupBy = $data['groupBy'];
        $rawLogs = $this->leadLogs->getManagerActivity(self::USERS, $timestampFrom, $timestampTo)->toArray();
        
        $logsGrouped = [];
        
        foreach($rawLogs as $log){
            // 1. Определяем менеджера
            $userId = $log['user_id'];
            $managerLabel = self::USERS_LABELS[$userId] ?? "Менеджер ID {$userId}";
            // 2. Определяем статус
            $pipelineId = $log['pipeline_id'];
            $statusId = $log['status_id'];
            $statusLabel = self::STATUSES_LABELS[$pipelineId][$statusId] ?? "Статус ID {$statusId}";
            
            if($groupBy === 'status'){
                // 3. Сразу собираем финальную структуру
                $logsGrouped[$managerLabel][$statusLabel][] = $log;
            }else if($groupBy === 'leadId'){
                $logsGrouped[$managerLabel][$log['amo_lead_id']][] = ["label" => $statusLabel, ...$log];
            }
        }
        
        return $logsGrouped;
    }
}