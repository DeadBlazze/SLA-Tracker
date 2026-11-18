<?php
namespace App\Services\amoCRM;

use GuzzleHttp\Client;
class RequisitesAutocomplete{
    protected Client $client;

    public function __construct() {
        $this->client = app('amocrm.client');
    }
    public function update($actualInn, $previousInn, $leadId){
        if($actualInn && $actualInn !== $previousInn){
            $token = config('services.dadata.token');
            $response = $this->client->post("https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party",[
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $token
                ],
                'json' => ["query"=> $actualInn, "branch_type"=> "MAIN"]
            ]);
            $dadata = json_decode((string) $response->getBody(), true);
            $suggestions = $dadata['suggestions'][0];
            $amoFieldsToUpdate = [];

            // Проверяем тип контрагента: 'INDIVIDUAL' для ИП, 'LEGAL' для юрлиц
            $companyType = $suggestions['data']['type'] ?? '';

            if ($companyType === 'INDIVIDUAL') {
                // Логика для ИП
                array_push($amoFieldsToUpdate, 
                    [
                        'field_id' => 726461, // ФИО руководителя
                        'values'   => [['value' => $suggestions['data']['name']['full'] ?? '']],
                    ],
                    [
                        'field_id' => 726463, // Должность руководителя
                        'values'   => [['value' => $suggestions['data']['opf']['full'] ?? 'Индивидуальный предприниматель']],
                    ]
                );
            } elseif ($companyType === 'LEGAL') {
                // Логика для Юридических лиц (ООО, АО и т.д.)
                array_push($amoFieldsToUpdate, 
                    [
                        'field_id' => 701571, // КПП
                        'values'   => [['value' => $suggestions['data']['kpp'] ?? '']],
                    ],
                    [
                        'field_id' => 726461, // ФИО руководителя
                        'values'   => [['value' => $suggestions['data']['management']['name'] ?? '']],
                    ],
                    [
                        'field_id' => 726463, // Должность руководителя
                        'values'   => [['value' => $suggestions['data']['management']['post'] ?? '']],
                    ]
                );
            }

            // Общие поля (одинаковые для ИП и Юрлиц)
            array_push($amoFieldsToUpdate,
                [
                    'field_id' => 701575, // Название компании
                    'values'   => [['value' => $suggestions['value'] ?? '']],
                ],
                [
                    'field_id' => 701589, // Юр. Адрес
                    'values'   => [['value' => $suggestions['data']['address']['unrestricted_value'] ?? '']],
                ],
                [
                    'field_id' => 701567, // ОКПО
                    'values'   => [['value' => $suggestions['data']['okpo'] ?? '']],
                ],
                [
                    'field_id' => 701565, // ОГРН / ОГРНИП
                    'values'   => [['value' => $suggestions['data']['ogrn'] ?? '']],
                ],
                [
                    'field_id' => 732539, // Обязательное техническое поля для этого скрипта
                    'values'   => [['value' => $actualInn]],
                ]
            );
            
            $token = config('services.amocrm.token');
            $response = $this->client->patch("/api/v4/leads/{$leadId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => [
                    'custom_fields_values' => $amoFieldsToUpdate
                ]
            ]);
            
        }
        
    }
}