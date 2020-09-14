<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskListUser extends Model
{
    protected $table = 'task_list_users';
    protected $guarded = [];
    public $timestamps = false;

    public static function GetTaskListUsers($taskID)
    {
        $checkIfHasTaskListUsers = TaskListUser::where('task_id', $taskID)->first();

        if (isset($checkIfHasTaskListUsers->group_id) && ! is_null($checkIfHasTaskListUsers->group_id)) {
            $users = GroupUser::groupID($checkIfHasTaskListUsers->group_id)->get();

            if (count($users))
                return $users;
            else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'There is no users in verify list has ID: ' . $checkIfHasTaskListUsers->group_id,
                    'statusType'      => "danger"
                ]);

        } else {
            $defaultUserList = Group::groupName('list_default')->first();

            if ($defaultUserList) {
                $users = GroupUser::groupID($defaultUserList->id)->get();

                if (count($users))
                    return $users;
                else
                    return \Redirect::Route('dashboard')->with([
                        'status'    => 'There is no users in verify list has ID: ' . $defaultUserList->id,
                        'statusType'      => "danger"
                    ]);
            } else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'There is not default list for verify persons',
                    'statusType'      => "danger"
                ]);
        }
    }
}
