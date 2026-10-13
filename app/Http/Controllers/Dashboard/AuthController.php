<?php

namespace App\Http\Controllers\Dashboard;
use Illuminate\Http\Request;
use App\Actions\Auth\LoginUserAction;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
class AuthController extends Controller{
    public function login(LoginRequest $request, LoginUserAction $action)
    {
        return response()->json(
            $action->execute($request->validated()),
            200
        );
    }
}