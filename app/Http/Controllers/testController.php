<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class testController extends Controller
{
    public function index(Request $request){
        $rawData = $request->input('_embedded');
        $events = $rawData['events'];
        $timeStamps = [];
        usort($events, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        foreach($events as &$event){
            $event['created_at'] = Carbon::createFromTimestamp($event['created_at'])->toDateTimeString();
        }
        unset($event);
        return response()->json($events, 200);
    }
}