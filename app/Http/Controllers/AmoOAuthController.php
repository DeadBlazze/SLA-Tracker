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
        error_log(123);
        return response(123, 200);
    }
}