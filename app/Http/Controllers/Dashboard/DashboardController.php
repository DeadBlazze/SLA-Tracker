<?php
namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\managerActivity\getActivityAction as ManagerActivityGetActivityAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Actions\Dashboard\getActivityAction;
use App\Http\Requests\getManagersActivityRequest;
class DashboardController extends Controller {
    public function getManagersActivity(getActivityAction $action, getManagersActivityRequest $request){
        return response()->json([$action->execute($request->validated())], 200);
    }
}