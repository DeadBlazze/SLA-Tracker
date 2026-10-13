<?php
namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\managerActivity\getActivityAction as ManagerActivityGetActivityAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Actions\Dashboard\getActivityAction;
class DashboardController extends Controller {
    public function getManagersActivity(getActivityAction $action){
        return response()->json([$action->execute()], 200);
    }
}