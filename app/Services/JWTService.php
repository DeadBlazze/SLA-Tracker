<?php

namespace App\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class JWTService {
    public function createToken(int $userId, int $roleId): array{
        $ttl = (int) config('jwt.ttl');
        $expiresAt = time() + $ttl;

        $payload = [
            'user_id' => $userId,
            'role_id' => $roleId,
            'iat' => time(),
            'exp' => $expiresAt,
        ];

        $token = JWT::encode(
            $payload,
            config('jwt.secret'),
            'HS256'
        );

        return [
            'access_token' => $token,
            'expires_at'   => $expiresAt, // Timestamp, когда токен умрет
        ];
    }

    public function getPayload(string $token): object{
        return JWT::decode(
            $token,
            new Key( config('jwt.secret'), 'HS256')
        );
    }
}