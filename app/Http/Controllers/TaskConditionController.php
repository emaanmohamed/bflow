<?php

namespace App\Http\Controllers;

use App\BeltoneCompany;
use App\ConditionDetails;
use App\Process;
use App\Task;
use App\TaskCondition;
use App\User;
use Illuminate\Http\Request;

class TaskConditionController extends Controller
{
    public function getTaskConditions()
    {
        $processes = Process::all();
        return view('admin.task_conditions.index', compact('processes'));
    }

    public function getTaskConditionsForProcessId(Request $request, $processId = null)
    {
        if (is_null($processId))
            return 'select process';

        $tasksConditions = TaskCondition::process($processId)->get();
        $tasks = Task::process($processId)->active()->get();
        $users = User::active()->get();

        if ($request->ajax()) {

            return view('admin.task_conditions.ajax.task_conditions',
                compact('tasks', 'tasksConditions', 'users'));
        }

        return view('admin.task_conditions.ajax.task_conditions',
            compact('tasks','tasksConditions', 'users'));
    }

    public function taskConditionUpdate(Request $request, $conditionId)
    {
        dd($request->all());
    }

    public function taskConditionRemove(Request $request)
    {
        ConditionDetails::find($request->condition_id)->delete();

        return response()->json(["message" => "Deleted Successfully.", "title" => 'Remove Condition Detail', "type" => 'success']);
    }

    public function taskConditionDetails($conditionId = null)
    {
        $conditionDetails = ConditionDetails::task($conditionId)->get();
        $tasks = Task::all();
        $users = User::all();

        return view('admin.task_conditions.ajax.condition_details',
                    compact('conditionDetails', 'tasks', 'users'));
    }

    public function conditionDetailsUpdate(Request $request, $conditionId = null)
    {
        if (is_null($conditionId))
            return response()->json(["message" => "Failed to update the record."]);
        else
            ConditionDetails::find($conditionId)->update(array_except($request->all(), ['_method']));

        return response()->json(["message" => "Updated Successfully.", "title" => 'Update Condition Detail', "type" => 'success']);
    }
}
