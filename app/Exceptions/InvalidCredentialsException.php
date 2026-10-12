<?php

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCredentialsException extends RuntimeException
{   
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => 'Неверные учётные данные',
        ], status: 401);
    }
}