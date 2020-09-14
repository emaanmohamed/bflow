<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContactPersonListUser extends Model
{
    protected $table = 'contact_person_list_users';
    protected $guarded = [];
    public $timestamps = false;

    public static function GetContactPersonList($userID)
    {
        $checkIfHasContactPersonList = ContactPersonListUser::where('user_id', $userID)->first();

        if (isset($checkIfHasContactPersonList->group_id) && ! is_null($checkIfHasContactPersonList->group_id)) {
            $users = GroupUser::groupID($checkIfHasContactPersonList->group_id)->get();

            if (count($users))
                return $users;
            else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'There is no users in verify list has ID: ' . $checkIfHasContactPersonList->group_id,
                    'statusType'      => "danger"
                ]);

        } else {
            $defaultContactPerson = Group::groupName('contact_person_default')->first();

            if ($defaultContactPerson) {
                $users = GroupUser::groupID($defaultContactPerson->id)->get();

                if (count($users))
                    return $users;
                else
                    return \Redirect::Route('dashboard')->with([
                        'status'    => 'There is no users in Contact Person list has ID: ' . $defaultContactPerson->id,
                        'statusType'      => "danger"
                    ]);
            } else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'There is not default list for Contact person',
                    'statusType'      => "danger"
                ]);
        }
    }
}
