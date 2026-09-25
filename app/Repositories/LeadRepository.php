<?php
namespace App\Repositories;
use Illuminate\Support\Facades\DB;
use App\Models\Lead;

class LeadRepository{
    public function add($data){
        $result = Lead::updateOrInsert($data);
        return $result;
    }
    public function updateStatus($data){
        
    }
}