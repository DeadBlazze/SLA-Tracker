<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRoleMiddleware
{
    /**
     * Все числа после 'role:' попадут в аргумент $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $payload = $request->attributes->get('jwt_payload');
        $userRoleId = (int) ($payload->role_id ?? 0);

        // Превращаем параметры '1', '4' в массив целых чисел [1, 4]
        $allowedRoleIds = array_map('intval', $roles);

        if (!in_array($userRoleId, $allowedRoleIds, true)) {
            return response()->json([
                'success' => false,
                'error'   => 'Forbidden: недостаточно прав'
            ], 403);
        }

        return $next($request);
    }
}