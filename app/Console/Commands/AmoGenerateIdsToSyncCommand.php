<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

class AmoGenerateIdsToSyncCommand extends Command
{
    protected $signature = 'amo:genIdsToSync {--hours= : Период восставновления истории в часах}';
    protected $description = 'Генерация пулла id сделок для восстановления истории статусов';

    public function handle()
    {
        $hoursCount = $this->option('hours');
        if(!$hoursCount){
            $this->error('Не передано количество часов int');
            return;
        }
        
        $lastSyncTimestamp = now()->subHours($hoursCount)->timestamp;
        $page = 1;

        while (true) {
            $baseDomain = config('services.amocrm.base_domain');
            $token = config('services.amocrm.token');
            $client = new Client([
                'base_uri' => "https://{$baseDomain}",
                'timeout'  => 5.0
            ]);
            // Запрашиваем с сортировкой по дате обновления (по убыванию)
            $response = $client->get("/api/v4/leads?page={$page}&limit=250&order[updated_at]=desc", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ]
            ]);

            $leadsChunk = json_decode((string) $response->getBody(), true)['_embedded']['leads'] ?? null;
            
            // Если API вернул пустой массив (дошли до конца всех сделок)
            if (empty($leadsChunk)) {
                break; 
            }

            $insertData = [];
            $shouldStopPaginating = false;

            foreach ($leadsChunk as $lead) {
                if ($lead['updated_at'] < $lastSyncTimestamp) {
                    $shouldStopPaginating = true;
                    break; // Выходим из цикла foreach
                }

                $insertData[] = [
                    'amo_lead_id' => $lead['id'],
                    'is_processed' => false,
                ];
            }

            // Сохраняем или обновляем собранный кусок
            if (!empty($insertData)) {
                DB::table('amo_backfill_ids')->upsert(
                    $insertData, 
                    ['amo_lead_id'], 
                    ['is_processed']
                );
            }

            // Если мы наткнулись на старые данные, прекращаем делать GET-запросы к API
            if ($shouldStopPaginating) {
                break; // Выходим из цикла while (true)
            }

            $page++;
            usleep(300000); // 200мс пауза между страницами для API лимитов
        }
    }
}
