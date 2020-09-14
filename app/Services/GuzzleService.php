<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class GuzzleService
{

    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function get($url)
    {
        try {
            $response = $this->client->get($url);
            $result = json_decode($response->getBody());
            return $result;
        } catch (ClientException $clientException) {
            $result = json_decode($clientException->getResponse()->getBody()->getContents());
            return $result;
        }

    }

    public function post($url, $params = [])
    {
        try {
            $response = $this->client->post($url, $params);
            $result = json_decode($response->getBody());
            return $result;

        } catch (RequestException $exception) {

            report($exception);
            if (is_null($exception->getResponse()))
                return redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);

            if (is_null(json_decode($exception->getResponse()->getBody())))
                return redirect()->route('dashboard')->with(['statusError' => 'Error: Please contact System Admin.']);

            $message = json_decode($exception->getResponse()->getBody())->message;
            return redirect()->route('dashboard')->with(['statusError' => $message ]);
        }


    }
}
