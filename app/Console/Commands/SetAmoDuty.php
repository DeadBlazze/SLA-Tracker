<?php
namespace App\Console\Commands;

use BcMath\Number;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetAmoDuty extends Command
{
    protected $signature = 'amo:duty {--user= : ID amo} {--show= : 1 чтобы показать текущего}';
    protected $description = 'Назначить дежурного менеджера amoCRM на неделю';

    public function handle()
    {   
        // 1. Если запросили показ
        $showData = $this->option('show');
        if ($showData) {
            $data = json_decode(Storage::disk('local')->get('amo_duty_manager.json'), true);
            $this->info('Текущий дежурный ID: ' . ($data['amo_user_id'] ?? 'не назначен'));
            return;
        }

        $userId = $this->option('user');

        if (!$userId) {
            $this->error('Передайте ID пользователя (--user=123) или флаг (--show)');
            return;
        }

        // 3. Сохранение
        $data = [
            'amo_user_id' => (int) $userId,
            'updated_at'  => now()->toDateTimeString(),
        ];

        Storage::disk('local')->put('amo_duty_manager.json', json_encode($data, JSON_PRETTY_PRINT));
        $this->info("Успешно: " . json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}