<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Carbon\Carbon;

class MegafonWebhookController extends Controller
{
    private const TARGET_TRUNK = '9210802077'; // Целевой номер без префиксов

    public function handle(Request $request)
    {
        // 1. Быстрый ответ 200 на все остальные команды ВАТС (contact, accounts, event и др.)
        if ($request->input('cmd') !== 'history') {
            return response('OK', 200);
        }

        // 2. Проверка токена интеграции
        $expectedToken = config('services.megafon.crm_token');
        if ($expectedToken && $request->input('crm_token') !== $expectedToken) {
            return response('Unauthorized', 401);
        }

        // 3. Фильтрация по номеру транка (поле diversion)
        $diversionRaw = (string) $request->input('diversion', '');
        $calledClean  = preg_replace('/\D/', '', $diversionRaw);
        // Если звонок прошел через другой номер ВАТС — игнорируем
        if (!str_contains($calledClean, self::TARGET_TRUNK)) {
            return response('Ignored: other trunk', 200);
        }

        // 4. Форматирование номеров
        // Клиентский номер (добавляем ведущий +)
        $callerClean = preg_replace('/\D/', '', (string) $request->input('phone', ''));
        $callerPhone = !empty($callerClean) ? '+' . $callerClean : '';

        // Номер, на который позвонили (формат из примера коллтрекинга: 89539396999 или 7...)
        $calledPhone = $calledClean;

        // 5. Обработка времени старта (формат Мегафона: YYYYmmddTHHMMSSZ -> в ISO-8601 с MSK +03:00)
        $rawStart = (string) $request->input('start');
        try {
            // Парсим UTC штамп Z и приводим к таймзоне +03:00
            $eventTime = Carbon::createFromFormat('Ymd\THis\Z', $rawStart, 'UTC')
                ->setTimezone('Europe/Moscow')
                ->toIso8601String();
        } catch (\Throwable) {
            $eventTime = now()->setTimezone('Europe/Moscow')->toIso8601String();
        }

        // 6. Определение статуса пропущенного (Success = успешный, остальные = пропущен/сорван)
        $status = (string) $request->input('status', '');
        $isMissed = ($status !== 'Success');

        // 7. Сборка объекта строго по спецификации коллтрекинга
        $payload = [
            'source'           => 'custom_crm',
            'external_call_id' => (string) $request->input('callid'),
            'event_time'       => $eventTime,
            'direction'        => (string) $request->input('type', 'in'),
            'caller_phone'     => $callerPhone,
            'called_phone'     => $calledPhone,
            'duration_sec'     => (int) $request->input('duration', 0),
            'is_missed'        => $isMissed,
        ];

        // 8. Отправка в коллтрекинг
        $token = config('services.calltracking_one.token');
        $client = new Client([
            'timeout' => 3.0,
        ]);
        try {
            $client->post('https://calltreckingone.ru/api_v1/calls/import.php', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Calltracking webhook delivery failed', [
                'callid' => $payload['external_call_id'] ?? null,
                'error'  => $e->getMessage(),
            ]);
        }

        // Обязательный синхронный ответ 200 для АТС
        return response('OK', 200);
    }
}
