<?php
namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\JWTService;
use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use App\Services\amoCRM\TaskFactory;
class testController extends Controller
{   
    public function __construct(
        private JWTService $jwtservice,
        private TaskFactory $taskFactory
    ){}
    public function index(Request $request){
        $this->taskFactory->execute(9088966,73139654,123, 123);
        // $token = JWT::encode(
        //     [
        //         'user_id' => 1,
        //         'role_id' => 1,
        //         'iat' => 1794470000,
        //         'exp' => 1794466373,
        //     ],
        //     config('jwt.secret'),
        //     'HS256'
        // );
        // dd($token);
        // $token = $request->input("token");
        // return response()->json($this->jwtservice->getPayload($token));
    }

}