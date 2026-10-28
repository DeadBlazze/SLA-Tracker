<?php
namespace App\Actions\Dashboard;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class getCallsAnalyticAction {
    private const ATS_USERS = [
        "9210812077" => ["username" => "9210812077", "alias" => "2ГИС"],
        "9210802077" => ["username" => "9210802077", "alias" => "ВК"],
        "9212482866" => ["username" => "9212482866", "alias" => "ЯБизнес"],
        "9532639075" => ["username" => "kazakova_oksana", "alias" => "ЯУслуги 2"]
    ];
    public function execute($data){
        $dateFromRaw = $data['datetimeFrom'];
        $dateToRaw = $data['datetimeTo'];  
        $phonesArray = $data['phones'];
        $fromDatetime= Carbon::parse($dateFromRaw)->utc()->toDateTimeString();
        $toDatetime = Carbon::parse($dateToRaw)->utc()->toDateTimeString();

        $api_key = config('services.megafon.api_key');
        $client = new Client([
            'timeout' => 3.0,
        ]);
        $history = [];
        foreach($phonesArray as $phone){
            $username = self::ATS_USERS[$phone]["username"] ?? null;
            if(!$username) return response()->json(['status' => 'error', 'message' => 'Невалидный номер телефона'], 400);
            $response = $client->request('GET', "https://vats675508.megapbx.ru/crmapi/v1/history/json?user={$username}&type=in&start={$fromDatetime}&end={$toDatetime}", [
                'headers' => [
                    "X-API-KEY" => $api_key
                ]
            ]);
            $json = (string) $response->getBody();
            $calls = json_decode((string) $json, 1);
            if (is_array($calls)) {
                $history[$phone] = $calls;
            }else{
                $history[$phone] = [];
            }
        }
        return $history;
        error_log(123);
    } 
}