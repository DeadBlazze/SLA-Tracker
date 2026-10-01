<?php

namespace App\Services\amoCRM;

class ResolveLeadSourceService{
    private const PROMO_SOURCES_IDS = [
        [
            "id"=> 691357,
            "value"=> "2ГИС звонок",
            "phone"=>"89210812077"
        ],
        [
            "id"=> 691361,
            "value"=> "ВК звонок",
            "phone"=> "89210802077"
        ],
        [
            "id"=> 691337,
            "value"=> "Ябизнес звонок",
            "phone" => "89212482866"
        ],
        [
            "id"=> 691367,
            "value"=> "Сайт",
            "phone" => "89539396999"
        ],
        [
            "id"=> 696157,
            "value"=> "ЯУслуги 2",
            "phone" => "89532639075"
        ]
    ];
    public function get(array $phoneFields){
        $promo_source = [];

        foreach($phoneFields as $name=>$value){
            $phoneAmo = $value[0]['value'] ?? null;
            if(!$phoneAmo) continue;
            foreach(self::PROMO_SOURCES_IDS as $source){
                $phone = $this->normalizePhoneNum($phoneAmo);
                if(!$phone){
                    error_log(321);
                    break;
                }else{
                    if($source['phone'] == $phone){
                        $promo_source['promo_source_name'] = $source['value'] ?? null;
                        $promo_source['promo_source_enum_id'] = $source['id'] ?? null;
                        $promo_source['source_phone'] = $source['phone'] ?? null;
                        break 2;
                    }
                    error_log(123);
                    continue;
                }
            }
        }
        return $promo_source;
    }
    public function normalizePhoneNum($phoneNum){
        if (!$phoneNum) {
            return null;
        }
        // Оставляем только цифры
        $digits = preg_replace('/\D+/', '', $phoneNum);

        // Если 11 цифр и начинается с 7 или 8 — берем последние 10
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'])) {
            return '8' . substr($digits, -10);
        }

        // Если ввели 10 цифр (например, 9991234567)
        if (strlen($digits) === 10) {
            return '8' . $digits;
        }

        // Некорректный номер или короткий добавочный — возвращаем null или очищенные цифры
        return null;
    }

}