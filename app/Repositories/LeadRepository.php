<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\Lead;

class LeadRepository{
    public function updateOrInsert($data){
        $result = Lead::updateOrInsert($data);
        return $result;
    }
    public function update($data){
        $result = Lead::where("amo_lead_id",$data['amo_lead_id'])->update($data);
        return $result;
    }
}