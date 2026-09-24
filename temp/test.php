<?php
$client = new Client();
$headers = [
  'Content-Type' => 'application/json',
  'Authorization' => 'Bearer ',
  'Cookie' => 'server_time=1790156590; session_id=51iujcm2o9ajsqirumo87mc195; user_lang=ru; fb_dp=1'
];
$body = '{
  "custom_fields_values": [
    {
      "field_id": 726901,
      "values": [
        {
          "value": 1789891503
        }
      ]
    }
  ]
}';
$request = new Request('PATCH', 'https://tkdvina.amocrm.ru/api/v4/leads/29156147', $headers, $body);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
