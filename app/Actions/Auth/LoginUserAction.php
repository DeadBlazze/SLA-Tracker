<?php
namespace App\Actions\Auth;
use App\Services\JWTService;
use App\Repositories\UserRepository;
use App\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

class LoginUserAction {
    public function __construct(
        private JWTService $jwtService,
        private UserRepository $users
    ) {}
    public function execute(array $data): array{
        $user = $this->users->findByLogin($data['login']);
        if(!$user || !Hash::check($data['password'], $user->password)){
            throw new InvalidCredentialsException();
        }
        $token = $token = $this->jwtService->createToken($user->id, $user->role_id);
        return [
            'success' => true,
            'token' => $token
        ];
    }
}