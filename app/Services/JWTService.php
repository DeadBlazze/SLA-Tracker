<?php

namespace App\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class JWTService {
    public function createToken(int $userId, int $roleId): string{
        $ttl = (int) config('jwt.ttl');

        $payload = [
            'user_id' => $userId,
            'role_id' => $roleId,
            'iat' => time(),
            'exp' => time() + $ttl,
        ];
        return JWT::encode(
            $payload,
            config('jwt.secret'),
            'HS256'
        );
    }

    public function getPayload(string $token): object{
        return JWT::decode(
            $token,
            new Key( config('jwt.secret'), 'HS256')
        );
    }
}