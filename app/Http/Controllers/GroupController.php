<?php

namespace App\Http\Controllers;

use App\Group;
use App\GroupUser;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GroupController extends Controller
{
    public function getGroups()
    {
        $groups = Group::all();
        return view('admin.groups.index', compact('groups'));
    }

    public function getGroupUsers(Request $request, $groupId)
    {
        if ($request->ajax()) {
            $users = GroupUser::groupID($groupId)->get();
            $activeUsers = User::active()->get();
            return view('admin.groups.ajax.users', compact('users', 'activeUsers', 'groupId'));
        }
    }

    public function getUsersForGroup(Request $request, $groupId)
    {
        if ($request->ajax()) {
            $users = GroupUser::groupID($groupId)->get();
            return view('admin.groups.ajax.refresh_users', compact('users', 'groupId'));
        }
    }

    public function addUserToGroup(Request $request)
    {
        if ($request->ajax()) {
            $checkIfExist = GroupUser::user($request->user_id)->groupID($request->group_id)->get();

            if (count($checkIfExist)) {
                return response()->json([
                    'message' => 'The user already exist.', "title" => "Add User to Group", "type" => 'warning'
                ]);
            } else {
                GroupUser::create($request->all());
                return response()->json([
                    'message' => 'User has been Added Successfully', "title" => "Add User to Group", "type" => 'success'
                ]);
            }
        }
    }

    public function removeUserFromGroup(Request $request)
    {
        GroupUser::user($request->user_id)->groupID($request->group_id)->delete();
        return response()->json(["message" => "Deleted Successfully.", "title" => 'Remove User From Group', "type" => 'success']);
    }
}
