<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('amocrm.client', function ($app) {
            // Настраиваем логику повторов
            $retryMiddleware = Middleware::retry(
                function (
                    $retries,
                    RequestInterface $request,
                    ResponseInterface $response = null,
                    \Exception $exception = null
                ) {
                    // Максимум 3 попытки
                    if ($retries >= 3) {
                        return false;
                    }

                    // Если код 429 пришел в объекте ответа
                    if ($response && $response->getStatusCode() === 429) {
                        return true;
                    }

                    // Если код 429 завернут в исключение Guzzle (ClientException/RequestException)
                    if ($exception instanceof RequestException && $exception->hasResponse()) {
                        if ($exception->getResponse()->getStatusCode() === 429) {
                            return true;
                        }
                    }

                    return false;
                },
                function ($retries) {
                    // Задержка: 1 сек, 2 сек, 3 сек
                    return $retries * 1000;
                }
            );

            // Создаем стек обработчиков и добавляем нашу middleware
            $stack = HandlerStack::create();
            $stack->push($retryMiddleware);

            // Возвращаем готовый клиент
            return new Client([
                'base_uri' => "https://" . config('services.amocrm.base_domain'),
                'timeout'  => 5.0,
                'handler'  => $stack,
                'headers'  => [
                    'Authorization' => 'Bearer ' . config('services.amocrm.token'),
                    'Content-Type'  => 'application/json',
                ]
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
