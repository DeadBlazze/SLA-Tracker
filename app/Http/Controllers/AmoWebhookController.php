<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Actions\AmoWebhooks\ProcessAmoLeadWebhookAction;

class AmoWebhookController {
    public function handle(Request $request, ProcessAmoLeadWebhookAction $action){
        $action->handle($request->all());
        
        return response()->json(['status' => 'ok'],200);
    }
}