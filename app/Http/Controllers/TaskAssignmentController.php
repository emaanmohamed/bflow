<?php

namespace App\Http\Controllers;

use App\Group;
use App\Task;
use App\TaskAssignment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TaskAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $taskAssignments = TaskAssignment::all();
        $groups = Group::all();
        $tasks = Task::all();

        if ($request->ajax()) {
            return view('admin.task_assignment.ajax.display_task_assignments',
                        compact('taskAssignments', 'groups', 'tasks'));
        }

        return view('admin.task_assignment.index', compact('taskAssignments', 'groups', 'tasks'));
    }

    public function removeTaskAssignment(Request $request)
    {
        if ($request->ajax()) {
            TaskAssignment::find($request->id)->delete();
            return response()->json(["message" => "Removed Successfully.", "title" => 'Remove Assigned Group from Task', "type" => 'success']);
        }
    }
}
