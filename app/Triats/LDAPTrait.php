<?php

namespace App\Triats;

use App\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;

trait LDAPTrait {


    /**
     * Used to check the user in our database or not
     * if yes (we will let him login)
     * if not (we will create a new user for him and then let him login)
     * @param $request
     */
    private function checkUser($request, $isUsername)
    {
        if ($isUsername === true)
            $user = User::where('username', $request->email)->get();
        else
            $user = User::where('email', $request->email)->get();

        if (isset($user[0]) && count($user)) {
            Auth::loginUsingId($user[0]->id);
        } else {
            $this->createUser($request);
        }
    }


    /**
     * Used to create a new user by using active directory data but if we got null from active directory
     * in this case we will fill the rest of data from request
     * @param $request
     */
    private function createUser($request)
    {
        $data = $this->getDataFromActiveDirectory($request);

        if (! is_null($data)) {
            $user = User::create([
                'email' => $request->email,
                'name' => $data->name,
                'username' => $data->username,
                'department' => $data->department,
                'password' => '',
            ]);
        } else {
            $user = User::create([
                'email' => $request->email,
                'name' => $request->email,
                'password' => '',
            ]);
        }

        Auth::loginUsingId($user->id);
    }


    /**
     * Used to retrieve user data from active directory
     * @param $request
     * @return array|null
     */
    private function getDataFromActiveDirectory($request)
    {
        $client = new Client(['base_uri' => env('LDAP_API_SERVICE_URL')]);

        if (strpos($request->email, '@'))
            $response = $client->request('GET', 'getUserMainInfoByEmail/' . $request->email);
        else
            $response = $client->request('GET', 'getUserMainInfoByUsername/' . $request->email);

        if ($response->getStatusCode() == 200)
            return \GuzzleHttp\json_decode($response->getBody());

        return null;
    }
}