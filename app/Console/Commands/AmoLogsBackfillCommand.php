<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Actions\AmoWebhooks\RestoreStatusLogsAction;
use Throwable;

class AmoLogsBackfillCommand extends Command
{
    protected $signature = 'amo:backfill {--file=storage\app\private\leads.txt : Путь к файлу со сделками} {--sleep=300 : Задержка между сделками в миллисекундах} {--limit=} {--offset=0}';
    protected $description = 'Прямой синхронный бэкфилл сделок через RestoreStatusLogsAction без очередей';

    public function handle(RestoreStatusLogsAction $restoreAction)
    {
        $filePath = $this->option('file');
        $ids = $this->loadIdsFromFile($filePath);
        $totalFound = count($ids);

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $offset = $this->option('offset') ? (int) $this->option('offset') : 0;
        $sleepMs = (int) $this->option('sleep');

        if($limit === null){
            $this->error('Не передан лимит');
            return;
        }

        $targetIds = array_slice($ids, $offset, $limit);
        $count = count($targetIds);
        $this->info("Всего в файле: {$totalFound}. К обработке: {$count} (смещение: {$offset}).");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $successCount = 0;
        $failCount = 0;
        $failedIds = [];
        foreach ($targetIds as $index => $leadId) {
            $leadId = (int) $leadId;

            try {
                // Вызываем напрямую текущий Action
                $restoreAction->execute($leadId);
                $successCount++;
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

            // Пауза для соблюдения лимитов API amoCRM (7 запросов в секунду)
            if ($sleepMs > 0 && $index < $count - 1) {
                usleep($sleepMs * 1000);
            }
        }
        $bar->finish();
        $this->newLine(2);

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
