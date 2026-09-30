<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Actions\AmoWebhooks\UpdateStatusAction;
use App\Actions\AmoWebhooks\DeleteLeadAction;
use App\Actions\AmoWebhooks\AddLeadAction;

class AmoOAuthController {

    public function callback(Request $request){
        return response()->json(['error'=>'Данный endpoint сейчас недоступен'],200);
    }
}