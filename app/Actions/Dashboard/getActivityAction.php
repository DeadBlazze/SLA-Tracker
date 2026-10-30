<?php
namespace App\Actions\Dashboard;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStatusLogRepository;
use App\Support\AmoUsers;
use App\Support\AmoPipelines;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

class getActivityAction{
    public function __construct(
        public LeadRepository $leads,
        public LeadStatusLogRepository $leadLogs
    ){}
    private const USERS = AmoUsers::DATA['USERS'];
    private const USERS_LABELS = AmoUsers::DATA['USERS_LABELS'];
    private const STATUSES_LABELS = AmoPipelines::DATA['STATUSES_LABELS'];
    // private const STATUSES_FOR_SORT = AmoPipelines::DATA['STATUSES_FOR_SORT'];

    public function execute($data){
        $dateFromRaw = $data['datetimeFrom'];
        $dateToRaw = $data['datetimeTo'];
        
        $fromDatetime= Carbon::parse($dateFromRaw)->utc()->toDateTimeString();
        $toDatetime = Carbon::parse($dateToRaw)->utc()->toDateTimeString();
        
        $groupBy = $data['groupBy'];
        $rawLogs = $this->leadLogs->getManagerActivity(self::USERS, $fromDatetime, $toDatetime)->toArray();
        if(empty($rawLogs)){
            return ["status" => "error", "message" => "Нет данных за выбранный период"];
        }
        $logsGrouped = [];
        
        if($groupBy === 'status'){
            $tempArray = [];
            // 1. Собираем черновой массив с композитными ключами
            foreach ($rawLogs as $log) {
                $userId = $log['user_id'];
                $managerLabel = self::USERS_LABELS[$userId] ?? "Менеджер ID {$userId}";
                
                $pipelineId = $log['pipeline_id'];
                $statusId = $log['status_id'];
                
                $tempArray[$managerLabel]["{$pipelineId}.{$statusId}"][] = $log;
            }

            // 2. Создаем карту порядка на основе STATUSES_LABELS
            // Получится массив вида ['9088966.73140342' => 0, '9088966.142' => 1, ...]
            $statusSortOrder = [];
            $sortIndex = 0;
            foreach (self::STATUSES_LABELS as $pId => $statuses) {
                foreach ($statuses as $sId => $label) {
                    $statusSortOrder["{$pId}.{$sId}"] = $sortIndex++;
                }
            }

            // 3. Сортируем данные и формируем итоговый массив $logsGrouped
            $logsGrouped = [];
            $leadIdsWithProfit =[];
            foreach ($tempArray as $managerLabel => $statusesData) {
                
                // Сортируем статусы текущего менеджера по созданной карте
                uksort($statusesData, function($a, $b) use ($statusSortOrder) {
                    // Если статуса нет в словаре, отправляем его в самый конец (вес 999999)
                    $weightA = $statusSortOrder[$a] ?? 999999;
                    $weightB = $statusSortOrder[$b] ?? 999999;
                    return $weightA <=> $weightB;
                });

                // Переписываем ключи из "9088966.142" в понятные названия из словаря
                foreach ($statusesData as $compositeKey => $logs) {
                    if($compositeKey === "9089018.73143098" || $compositeKey === "9089018.73143102" || $compositeKey === "9089018.142"){
                        foreach($logs as $log){
                            $leadIdsWithProfit[$managerLabel][$log['amo_lead_id']] = true; 
                        }
                    }
                    [$pipelineId, $statusId] = explode(".", $compositeKey);
                    $statusLabel = self::STATUSES_LABELS[$pipelineId][$statusId] ?? "Статус ID {$statusId}";
                    
                    $logsGrouped[$managerLabel][$statusLabel] = $logs;
                }
            }

            // Собираем сумму net_profit
            $netProfitSum = [];
            foreach ($leadIdsWithProfit as $managerLabel => $managerIds) {
                $user_id = array_search($managerLabel, self::USERS_LABELS);
                if(!$user_id) continue;
                
                $ids = array_keys($managerIds);

                $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT SUM(net_profit) AS profit FROM leads WHERE amo_lead_id IN ($idsPlaceholder) AND responsible_user_id = ?";
                
                $sum = DB::select($sql, [...$ids, $user_id]);
                $netProfitSum[$managerLabel] = round((float) ($sum[0]->profit ?? 0), 2);
            }
        }else if($groupBy === 'leadId'){
            foreach($rawLogs as $log){
                // 1. Определяем менеджера
                $userId = $log['user_id'];
                $managerLabel = self::USERS_LABELS[$userId] ?? "Менеджер ID {$userId}";
                // 2. Определяем статус
                $pipelineId = $log['pipeline_id'];
                $statusId = $log['status_id'];
                $statusLabel = self::STATUSES_LABELS[$pipelineId][$statusId] ?? "Статус ID {$statusId}";
                
                $logsGrouped[$managerLabel][$log['amo_lead_id']][] = ["label" => $statusLabel, ...$log];
            }
        }
        return ["status" => "ok", "data" => $logsGrouped, "netProfitSum" => $netProfitSum];
    }
}