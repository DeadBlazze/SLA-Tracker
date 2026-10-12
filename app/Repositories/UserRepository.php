<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserRepository{
    public function findByLogin($login){
        return User::where(['login'=>$login])->first();
    }
}