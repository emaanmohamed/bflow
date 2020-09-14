<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VerifyListUser extends Model
{
    protected $table = 'verify_list_users';
    protected $guarded = [];
    public $timestamps = false;

    public static function GetVerifyUsers($userID)
    {
        $checkIfHasVerifyList = VerifyListUser::where('user_id', $userID)->first();

        if (isset($checkIfHasVerifyList->group_id) && ! is_null($checkIfHasVerifyList->group_id)) {
            $users = GroupUser::groupID($checkIfHasVerifyList->group_id)->get();

            if (count($users))
                return $users;
            else
                return VerifyListUser::GetDefaultVerifyList();

        } else {
            return VerifyListUser::GetDefaultVerifyList();
        }

    }

    public static function GetDefaultVerifyList()
    {
        $defaultVerifyList = Group::groupName('verify_default')->first();

        if ($defaultVerifyList) {
            $users = GroupUser::groupID($defaultVerifyList->id)->get();

            if (count($users))
                return $users;
            else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'There is no users in verify list has ID: ' . $defaultVerifyList->id,
                    'statusType'      => "danger"
                ]);
        } else
            return \Redirect::Route('dashboard')->with([
                'status'    => 'There is not default list for verify persons',
                'statusType'      => "danger"
            ]);

    }
}
