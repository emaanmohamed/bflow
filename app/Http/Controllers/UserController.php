<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUsers(Request $request)
    {
        $users = User::all();

        if ($request->ajax()) {
            return view('admin.users.ajax.display_users', compact('users'));
        }

        return view('admin.users.index', compact('users'));
    }

    public function update(Request $request, $userId)
    {
        User::find($userId)->update([
            'is_admin' => ($request->is_admin === "true") ? 1 : 0,
            'has_reassign' => ($request->has_reassign === "true") ? 1 : 0,
        ]);

        return response()->json([
            'type'      => 'success',
            'title'     => 'Updated Successfully',
            'message'   => "User data has been updated Successfully"
        ]);
    }
}
