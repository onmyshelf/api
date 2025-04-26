<?php

class AI extends GlobalAI
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;
    protected $lang;


    public function __construct()
    {
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
        
        $this->apiKey = Config::get('ai_openai_apikey');
        $this->model = Config::get('ai_openai_model');

        $this->lang = 'fr_FR';
    }


    protected function request($request)
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Do not speak just return JSON with keys in english and values in '.$this->lang.' if possible but do not translate'],
                ['role' => 'user', 'content' => $request]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        } else {
            return json_decode($response, true);
            //return $result['choices'][0]['message']['content'];
        }

        curl_close($ch);
    }
}
