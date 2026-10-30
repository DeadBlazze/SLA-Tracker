<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Actions\AmoWebhooks\ProcessAmoLeadWebhookAction;
use App\Actions\AmoWebhooks\RestoreStatusLogsActionV2;
class AmoWebhookController {
    public function handle(Request $request, ProcessAmoLeadWebhookAction $action){
        $action->handle($request->all());
        
        return response()->json(['status' => 'ok'],200);
    }
    public function restore(Request $request, RestoreStatusLogsActionV2 $action){
        $action->execute($request->input("id"));
        return response()->json(['status' => 'ok'],200);
    }
}