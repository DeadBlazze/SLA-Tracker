<?php
namespace App\Actions\Dashboard;
use Illuminate\Support\Facades\DB;
class getActivityAction{
    public function execute(){
        $rows = DB::select('SELECT * 
            FROM lead_status_logs 
            WHERE entered_at >= "2026-10-13 12:18:11"
            AND entered_at <= "2026-10-13 23:19:11" AND user_id IN ( 8038798, 8047285, 8047297, 12674562, 13970306, 13545438)
            ORDER BY id ASC;');
        return $rows;

    }
}