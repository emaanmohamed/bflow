<?php

namespace App\Http\Controllers;

use App\Triats\LDAPTrait;
use App\User;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LDAPLoginController extends Controller
{
    use LDAPTrait;

    public function login(Request $request)
    {
        try {
            $client = new Client();
            $isUsername = false;
            if (strpos($request->email, '@')) {
                $uri = env('LDAP_API_SERVICE_URL') . 'loginByEmail';
                $credentials = ['email' => $request->email, 'password' => $request->password];
            } else {
                $isUsername = true;
                $uri = env('LDAP_API_SERVICE_URL') . 'loginByUsername';
                $credentials = ['username' => $request->email, 'password' => $request->password];
            }

            Log::channel('login')->info($request->email);
//            Log::channel('login')->info($credentials);

            $response = $client->post($uri, [
                RequestOptions::JSON => $credentials
            ]);

            Log::channel('login')->info($response->getBody());

            if ($response->getStatusCode() == 200)
                $this->checkUser($request, $isUsername);

        } catch (RequestException $exception) {
            if (is_null($exception->getResponse()))
                return redirect()->back()->with(['status' => 'Please enter your credentials.',
                                                 'statusType' => 'danger']);

            if (is_null(json_decode($exception->getResponse()->getBody())))
                return redirect()->back()->with(['status' => 'Error: Please contact System Admin because we can\'t contact LDAP service right now :( "Response => Null (Empty Response)"',
                    'statusType' => 'danger']);

            $message = json_decode($exception->getResponse()->getBody())->message;
            return redirect()->back()->with(['status' => $message, 'statusType' => 'danger']);
        }

        return redirect()->route('dashboard');
    }

    public function getLogin()
    {
        $users = User::all();
        return view('login', compact('users'));
    }
}
