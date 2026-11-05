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
use Illuminate\Support\Facades\Log;

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
                    [$pipelineId, $statusId] = explode(".", $compositeKey);
                    $statusLabel = self::STATUSES_LABELS[$pipelineId][$statusId] ?? "Статус ID {$statusId}";
                    
                    $logsGrouped[$managerLabel][$statusLabel] = $logs;
                }
            }

            // Собираем сумму net_profit
            $netProfitSum = [];
            $managerIds = array_keys(self::USERS_LABELS);
            $idsPlaceholder = implode(',', array_fill(0, count($managerIds), '?'));
            
            $sql = "SELECT responsible_user_id, SUM(net_profit) AS net_profit FROM leads
            WHERE responsible_user_id IN ({$idsPlaceholder})
            AND pipeline_id = 9089018 AND status_id IN (73143098, 73143102, 142)
            AND cargo_loaded > ? AND cargo_loaded < ?
            GROUP BY responsible_user_id;";

            $params = [...$managerIds, $fromDatetime, $toDatetime];

            $rawProfit = DB::select($sql, $params);

            foreach($rawProfit as $sumStr){
                $managerLabel = self::USERS_LABELS[$sumStr->responsible_user_id] ?? "ID: {$sumStr->responsible_user_id}";
                $netProfitSum[$managerLabel] = round((float) $sumStr->net_profit, 2);
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