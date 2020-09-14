<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TaskCondition extends Model
{
    protected $table = 'task_conditions';
    protected $guarded = [];

    public function scopeTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeProcess($query, $processID)
    {
        return $query->where('process_id', $processID);
    }

    public function scopeCompanyID($query, $companyID)
    {
        if (! is_null($companyID)) {
            return $query->where('company_id', 'like', "%$companyID%");
        }
        return $query;
    }

    public function scopeWithoutCompanyID($query)
    {
        return $query->whereNull('company_id');
    }

    public function scopeActionType($query, $actionType)
    {

        return $query->where('action_type', $actionType);
    }

    public static function CheckConditionsOnTaskID($case, $taskID, $firstTask, $lastTask, $request, $processID, $logMSG = null)
    {

        $companyID = $case->company_id;

        if ($firstTask === true)
            $actionType = 'approve';
        else
            $actionType = checkActionType($request);


        $conditions = TaskCondition::task($taskID)->process($processID)
                                    ->actionType($actionType)->companyID($companyID)
                                    ->get();

        if (! count($conditions))
            $conditions = TaskCondition::task($taskID)->process($processID)
                ->actionType($actionType)->withoutCompanyID()
                ->get();

        if (! count($conditions) && $firstTask !== true && $lastTask != $taskID)
            dd("Contact System Admin: There's no condition in this task $taskID");

        for ($i = 0, $len = count($conditions); $i < $len; $i++) {
            $resultOfCondition = ConditionDetails::Check($conditions[$i]->id, $case, $logMSG);
        }
        return (isset($resultOfCondition) && $resultOfCondition !== false)
            ? $resultOfCondition : false;
    }

    public static function CheckConditionsOnTaskIDToSkip($isFirstTask, $request, $processID)
    {
        $case = PurchaseCase::find($request['case_id']);
        $actionType = 'approve';
        $taskID = $request['task_id'];
        $companyID = $case->company_id;

        $conditions = TaskCondition::task($taskID)->process($processID)
                                    ->actionType($actionType)->companyID($companyID)
                                    ->get();

        if (! count($conditions))
            $conditions = TaskCondition::task($taskID)->process($processID)
                ->actionType($actionType)->withoutCompanyID()
                ->get();

        if (! count($conditions) && $isFirstTask !== true)
            dd("Contact System Admin: There's no condition in this task $taskID");

        for ($i = 0, $len = count($conditions); $i < $len; $i++) {
            $resultOfCondition = ConditionDetails::Check($conditions[$i]->id, $case);
        }

        return (isset($resultOfCondition) && $resultOfCondition !== false)
            ? $resultOfCondition : false;
    }
}
