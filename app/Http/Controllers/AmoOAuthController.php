<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Actions\AmoWebhooks\UpdateStatusAction;
use App\Actions\AmoWebhooks\DeleteLeadAction;
use App\Actions\AmoWebhooks\AddLeadAction;

class AmoOAuthController {

    public function callback(Request $request){
        $leads = $request->input('leads');
        $leadKey = null;
        foreach($leads as $key => $value){
            $leadKey = $key;
        }
        switch ($leadKey){
            case 'status': $updateAction->handle($leads[0]);
                break;
            case 'add' : $addLeadAction->handle($leads[0]);
                break;
            case 'delete': $deleteLeadAction->handle($leads[0]);
                break;
            default:
                return response()->json(["error"=>"неизвестный вебхук"], 200);
                error_log($leadKey);
        }
        return response(123, 200);
    }
}