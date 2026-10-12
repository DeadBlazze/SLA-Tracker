<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\JWTService;
class testController extends Controller
{   
    public function __construct(
        private JWTService $jwtservice
    ){}
    public function index(Request $request){    
        return response()->json(['analytics' => 'binary', 'success' => true]);
    }

}