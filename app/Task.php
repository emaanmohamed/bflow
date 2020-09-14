<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    protected $table = 'tasks';
    protected $guarded = [];

    public function scopeProcess($query, $processID)
    {
        return $query->where('process_id', $processID);
    }

    public function scopeTask($query, $taskID)
    {
        return $query->where('id', $taskID);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeHasOrder($query)
    {
        return $query->whereNotNull('task_order');
    }

    public static function GetNextTaskIDFromNormalWorkFlow($currentTaskID, $processID)
    {
        $tasks = Task::process($processID)->hasOrder()->active()
            ->pluck("task_order", "id")->toArray();

        $values = array_values($tasks);
        $keys   = array_keys($tasks);
        if (isset($tasks[$currentTaskID])) {
            $currentTaskKey = $tasks[$currentTaskID];
        } else {
            return null;
        }

        $tasks = array_combine($values, $keys);

        // remove current task id and all tasks before from an array
        for ($i = $currentTaskKey; $i <= $currentTaskKey; --$i) {
            if (isset($tasks[$i])) {
                unset($tasks[$i]);
            }

            if ($i == 1) {
                break;
            }
        }

        $values = array_values($tasks);
        $keys   = array_keys($tasks);
        $tasks  = array_combine($values, $keys);
        // sort an array by value
        asort($tasks);
        // current used to get the first value has pointed
        return count($tasks) ? array_search(current($tasks), $tasks) : false;
    }

    public static function CheckIfIsFirstTaskOnTheWorkFlow($currentTaskID, $processID)
    {

        $checkIfIsFirstTask = Task::process($processID)->task($currentTaskID)->hasOrder()->active()
            ->get();

        if (isset($checkIfIsFirstTask[0]) && count($checkIfIsFirstTask)) {
            return ($checkIfIsFirstTask[0]->task_order == 1) ? true : false;
        }

        return false;
    }

    public static function GetLastTaskIDOnTheWorkFlow($processID)
    {
        $lastTaskID = Task::process($processID)->hasOrder()->active()
            ->orderBy('task_order', 'desc')->first();

        if ($lastTaskID) {
            return $lastTaskID->id;
        }

        return \Redirect::Route('dashboard')->with([
            'status'     => 'Cannot get the last task ID for the case. [Task::Class->Func(GetLastTaskIDOnTheWorkFlow)]',
            'statusType' => "danger"
        ]);
    }

    public static function GetCountOfAllStepsForProcessID($processID)
    {
        $countOfAllSteps = Task::process($processID)->hasOrder()->active()
            ->count();

        if ($countOfAllSteps) {
            return $countOfAllSteps;
        }

        return \Redirect::Route('dashboard')->with([
            'status'     => 'Cannot get count of all steps. [Task::Class->Func(GetCountOfAllStepsForProcessID)]',
            'statusType' => "danger"
        ]);
    }

    public static function GetPreviousTaskIDForTaskID($currentTaskID, $processID)
    {
        $taskOrderForCurrentTask = Task::process($processID)->task($currentTaskID)->hasOrder()->active()->first();

        if ($taskOrderForCurrentTask) {
            $taskOrderForCurrentTask = $taskOrderForCurrentTask->task_order;
        }

        if ($taskOrderForCurrentTask == 1) {
            return false;
        }

        if (!is_null($taskOrderForCurrentTask) && !empty($taskOrderForCurrentTask)) {
            $previousTaskID = Task::process($processID)->where('task_order', '<', $taskOrderForCurrentTask)
                ->hasOrder()->active()
                ->orderBy('task_order', 'desc')->first();
        }

        if (isset($previousTaskID)) {
            return $previousTaskID;
        }

        return false;
    }

    public static function GetNextTaskWithUserID($currentTaskID, $case, $request, $processID, $logMSG = null)
    {
	    try {

		    $nextTaskIDFromNormalWorkFlow = Task::GetNextTaskIDFromNormalWorkFlow($currentTaskID, $processID);


		    $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($currentTaskID, $processID);

		    $lastTask  = Task::GetLastTaskIDOnTheWorkFlow($processID);


		    $countOfAllSteps = Task::GetCountOfAllStepsForProcessID($processID);


		    $previousTaskIDFromNormalWorkFlow = Task::GetPreviousTaskIDForTaskID($currentTaskID, $processID);

		    $followAnyConditions = TaskCondition::CheckConditionsOnTaskID($case, $currentTaskID,
			    $firstTask, $lastTask, $request, $processID, $logMSG);
		    if ($currentTaskID == config('vacation_var.VACATION_LINE_MANAGER_TASK_ID')) {

			    $employeeEmail = User::select('email')->where('name', Auth::user()->name)->get();
			    $params = [
				    "email" => $employeeEmail[0]->email
			    ];
			    $client = new Client();
			    $response = $client->post(env('VACATION_BALANCE_API') . "getAdUser", [
				    RequestOptions::JSON => $params
			    ]);
			    $result = json_decode($response->getBody());
			    if (isset($result->managerName)) {
				    $managerName = $result->managerName;
			    } else {

				    return redirect()->back()->with(['statusError' => 'Please contact System Admin.']);
			    }

			    if (! empty($managerName)) {
				    $nextUserIDFromNormalWorkFlow = User::select('id')->where('name', $managerName)->pluck('id')->first();
			    } else {
				    $nextTaskIDFromNormalWorkFlow = config('vacation_var.VACATION_HR_TASK_ID');
				    $nextUserIDFromNormalWorkFlow = TaskAssignment::GetUserIDForTaskID($nextTaskIDFromNormalWorkFlow, $case , $logMSG);
			    }

		    } else {
			    $nextUserIDFromNormalWorkFlow = TaskAssignment::GetUserIDForTaskID($nextTaskIDFromNormalWorkFlow, $case, $logMSG);
		    }


		    if ($followAnyConditions === false) {
			    $logMSG['followAnyConditions'] = false;
			    $actualNextTaskID              = $nextTaskIDFromNormalWorkFlow;
			    if ($nextUserIDFromNormalWorkFlow != null) {
                    $actualNextUserID              = $nextUserIDFromNormalWorkFlow;
                } else {
			        dd("This user doesn't have line manager");
                }

		    } else {

			    $actualNextTaskID       = $followAnyConditions['routing_task_id'];
			    $doubleCheckIfFirstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($actualNextTaskID, $processID);

			    if ($doubleCheckIfFirstTask === true) {
				    $actualNextUserID = (isset($followAnyConditions['routing_user_id']) &&
					    !empty($followAnyConditions['routing_user_id']))
					    ? $followAnyConditions['routing_user_id']
					    : $case->created_by_user_id;
			    } else {
				    $actualNextUserID = (isset($followAnyConditions['routing_user_id']) && !empty($followAnyConditions['routing_user_id']))
					    ? $followAnyConditions['routing_user_id']
					    : ParticipatedCase::GetParticipatedUserIDForTaskID($processID, $case, $actualNextTaskID);
			    }
		    }

		    return [
			    'nextTaskIDFromNormalWorkFlow'     => $nextTaskIDFromNormalWorkFlow,
			    'nextUserIDFromNormalWorkFlow'     => $nextUserIDFromNormalWorkFlow,
			    'firstTask'                        => $firstTask,
			    'lastTask'                         => $lastTask,
			    'countOfAllSteps'                  => $countOfAllSteps,
			    'previousTaskID'                   => $currentTaskID,
			    'previousTaskIDFromNormalWorkFlow' => ($previousTaskIDFromNormalWorkFlow === false) ? $case->previous_task_id : $previousTaskIDFromNormalWorkFlow,
			    'followAnyConditions'              => ($followAnyConditions) ? true : false,
			    'routingDetails'                   => $followAnyConditions,  // return array [route_task_id, route_user_id,
			    'nextUserID'                       => $actualNextUserID,
			    'nextTaskID'                       => $actualNextTaskID,
			    'previousUserID'                   => $case->current_user_id,
		    ];
	    } catch (RequestException $exception) {

		    if (is_null($exception->getResponse()))
			    return redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);

		    if (is_null(json_decode($exception->getResponse()->getBody())))
			    return redirect()->route('dashboard')->with(['statusError' => 'Error: Please contact System Admin.']);

		    $message = json_decode($exception->getResponse()->getBody())->message;
		    return redirect()->route('dashboard')->with(['statusError' => $message ]);
	    }

    }


    public static function GetNextTaskWithUserIDToSkip($request, $case, $processID)
    {
        $currentTaskID       = $request['task_id'];
        $isFirstTask         = Task::CheckTaskIDIsFirstTaskForProcessID($currentTaskID, $processID);
        $followAnyConditions = TaskCondition::CheckConditionsOnTaskIDToSkip($isFirstTask, $request, $processID);

        if ($followAnyConditions === false && $isFirstTask !== true) {
            dd('no conditions please check with development team');
        }

        return [
            'previousUserID'      => $case->current_user_id,
            'previousTaskID'      => $currentTaskID,
            'followAnyConditions' => ($followAnyConditions) ? true : false,
            'routingDetails'      => $followAnyConditions,
            'nextUserID'          => is_null($followAnyConditions['routing_user_id'])
                ? TaskAssignment::GetUserIDForTaskID($followAnyConditions['routing_task_id'], $case)
                : $followAnyConditions['routing_user_id'],
            'nextTaskID'          => $followAnyConditions['routing_task_id']
        ];
    }

    public static function GetVerifyTaskID($taskID)
    {
        return Task::find($taskID)->verify_task_id;
    }

    public static function GetFirstTaskIDForProcessID($processID)
    {

        $task = Task::process($processID)->where('task_order', 1)->first();

        if (!is_null($task) || !empty($task)) {
            return $task->id;
        }

        dd('Cannot find first task ID for processID: '.$processID);
    }

    public static function CheckTaskIDIsFirstTaskForProcessID($taskID, $processID)
    {
        $task = Task::process($processID)->where('task_order', 1)->first();

        if (!is_null($task) || !empty($task)) {
            return $task->id == $taskID ? true : false;
        }

        return false;
    }
}
