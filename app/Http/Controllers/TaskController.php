<?php

namespace App\Http\Controllers;

use App\Process;
use App\Task;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TaskController extends Controller
{
    public function getTasks()
    {
        $processes = Process::all();
        return view('admin.tasks.index', compact('processes'));
    }

    public function getTaskForProcessId(Request $request, $processId = null)
    {
        if (is_null($processId))
            return 'select process';

        $tasks = Task::process($processId)->active()->get();
        $users = User::active()->get();

        if ($request->ajax()) {

            return view('admin.tasks.ajax.display_tasks', compact('tasks', 'users'));
        }

        return view('admin.tasks.ajax.display_tasks', compact('tasks', 'users'));
    }

    public function taskUpdate(Request $request, $taskId)
    {
        if ($request->ajax() && ! is_null($taskId) && ! empty($taskId)) {
            $_request = $request->all();
            $_request['is_active'] = ($_request['is_active'] == "true") ? 1 : 0;
            $_request['has_verify_option'] = ($_request['has_verify_option'] == "true") ? 1 : 0;

            Task::find($taskId)->update(array_except($_request, ['_method']));

            return response()->json([
                'type'      => 'success',
                'title'     => 'Updated Successfully',
                'message'   => "Task data has been updated Successfully"
            ]);
        }
    }

    public function taskRemove($taskId)
    {
        dd($taskId);
    }
}
