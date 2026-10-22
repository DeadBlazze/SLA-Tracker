<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Actions\AmoWebhooks\RestoreStatusLogsActionV2;
use Throwable;
use Illuminate\Support\Facades\DB;
class AmoLogsBackfillCommand extends Command
{
    // protected $signature = 'amo:backfill {--file=storage/app/private/leads.txt : Путь к файлу со сделками} {--sleep=300 : Задержка между сделками в миллисекундах} {--limit=} {--offset=0}';
    protected $description = 'Прямой синхронный бэкфилл сделок через RestoreStatusLogsAction без очередей';
    protected $signature = 'amo:backfill {--sleepMs=100}';
    public function handle(RestoreStatusLogsActionV2 $restoreAction)
    {   
        $sleepMs = (int) $this->option('sleepMs');
        $leads = DB::select("SELECT * FROM amo_backfill_ids WHERE is_processed = false");
        $count = count($leads);
        $this->info("В базе найдено {$count} сделок");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $successCount = 0;
        $failCount = 0;
        $failedIds = [];
        $successIds = [];

        foreach ($leads as $index => $lead) {
            $leadId = (int) $lead->amo_lead_id;

            try {
                // Вызываем напрямую текущий Action
                $restoreAction->execute($leadId);
                $successCount++;
                $successIds[] = $leadId;
            } catch (Throwable $e) {
                $failCount++;
                $failedIds[] = [
                    'id' => $leadId,
                    'error' => $e->getMessage()
                ];
                $this->newLine();
                $this->error("{$e->getMessage()}");
            }

            $bar->advance();
            
            // if ($sleepMs > 0 && $index < $count - 1) {
            //     usleep($sleepMs * 1000);
            // }
        }
        $bar->finish();
        $this->newLine(2);
        
        // МАССОВОЕ ОБНОВЛЕНИЕ processed В БД
        if (!empty($successIds)) {
            // Разбиваем на порции по 1000 штук (безопасно для MySQL/PostgreSQL)
            foreach (array_chunk($successIds, 1000) as $chunk) {
                DB::table('amo_backfill_ids')
                    ->whereIn('amo_lead_id', $chunk)
                    ->update(['is_processed' => true]);
            }
            $this->info("Флаг is_processed успешно обновлен для {$successCount} записей.");
        }

        $this->info("Обработка завершена. Успешно: {$successCount}, Ошибок: {$failCount}.");

        // Сохраняем сбойные ID в отдельный файл для повторного прогона
        if (!empty($failedIds)) {
            $failLogPath = storage_path('failed_backfill_ids.json');
            File::put($failLogPath, json_encode($failedIds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->warn("Список сбойных сделок сохранен в: {$failLogPath}");
        }
        return self::SUCCESS;
    }
    private function loadIdsFromFile(string $path): array{
        if (!File::exists($path)) {
            $this->error("Файл не найден: {$path}");
            return [];
        }    
        $content = File::get($path);
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $clearIdsArray = [];
        foreach($lines as $line){
            $clearIdsArray[] = trim($line);
        }
        return $clearIdsArray;
    }
}
