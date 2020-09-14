<?php

namespace App\Triats;

use App\Mail\PaymentCompleteEmail;
use App\Mail\PaymentEmail;
use App\Mail\PurchaseCompleteEmail;
use App\Mail\PurchaseEmail;
use App\PurchaseCase;
use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait NotificationTrait {

    private function NotifyUserViaEmail($case, $processID)
    {
        if ($processID == env('PURCHASE_PROCESS')) {

            $this->_sendMail($case, new PurchaseEmail($case));

        } elseif ($processID == env('PAYMENT_PROCESS')) {

            $this->_sendMail($case, new PaymentEmail($case));

        }
    }

    private function NotifyCompleteCaseViaEmail($case, $processID)
    {
        if ($processID == env('PURCHASE_PROCESS')) {

            $this->_sendMail($case, new PurchaseCompleteEmail($case), true);

        } elseif ($processID == env('PAYMENT_PROCESS')) {

            $this->_sendMail($case, new PaymentCompleteEmail($case), true);

        }
    }

    private function _sendMail($case, $object, $isCompleted = false)
    {
        if ($isCompleted === true) {

            if (isset($case->user->email)) {
                Mail::to($case->user->email)->send($object);
            }

        } else {

            if (isset($case->currentUser->email))
                Mail::to($case->currentUser->email)->send($object);

        }
    }

    private function SendMail($request)
    {
        try {
            $client = new Client();

            $uri = env('EMAIL_API_SERVICE_URL') . 'sendEmail';

            $response = $client->post($uri, [
                RequestOptions::JSON => $request
            ]);

            if ($response->getStatusCode() == 200)
                return \GuzzleHttp\json_decode($response->getBody());

        } catch (RequestException $exception) {
            if (is_null($exception->getResponse()))
                dd('no response');

            $message = json_decode($exception->getResponse()->getBody());

            if ($message)
                return $message->message;
        }

        return null;
    }
}