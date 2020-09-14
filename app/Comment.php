<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Comment extends Model
{
    protected $table = 'comments';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task', 'task_id');
    }

    public static function CreateCommentForCaseID($request, $caseId, $taskId, $processID, $participatedID)
    {
        if ($processID == env('PAYMENT_PROCESS'))
            $case = PaymentCase::findOrFail($caseId);
        elseif ($processID == env('PURCHASE_PROCESS'))
            $case = PurchaseCase::findOrFail($caseId);
        elseif ($processID == env('SETTLEMENT_PROCESS'))
            $case = SettlementCase::findOrFail($caseId);
        elseif ($processID == env('VACATION_PROCESS'))
            $case = VacationCase::findOrFail($caseId);

        $task = Task::find($taskId);
        $approval = '';
        $columnName = '';

        if ($task) {
            $columnName = isset($task->approval_column) ? $task->approval_column : null;
            $approval = ! is_null($columnName) || ! empty($columnName)
                ? (isset($request->$columnName) ? $request->$columnName : null)
                : null;
        }

        $comment = Comment::create([
            'comment'   => $request->comment,
            'process_id'  => $processID,
            'case_id'    => $caseId,
            'task_id'   => $case->task_id,
            'user_id'   => Auth::user()->id,
            'action_type'   => $approval,
            'participated_id'   => $participatedID
        ]);

        return $comment;
    }


    public static function AddNote($note, $caseId, $processID)
    {
        if ($processID == env('PURCHASE_PROCESS'))
            $case = PurchaseCase::findOrFail($caseId);
        else if ($processID == env('PAYMENT_PROCESS'))
            $case = PaymentCase::findOrFail($caseId);
        else if ($processID == env('SETTLEMENT_PROCESS'))
            $case = SettlementCase::findOrFail($caseId);

        $comment = Comment::create([
            'comment'   => $note,
            'process_id'  => $processID,
            'case_id'    => $caseId,
            'task_id'   => $case->task_id,
            'user_id'   => Auth::user()->id,
            'action_type'   => 3 // reassign
        ]);

        return $comment;
    }

    public function scopeCase($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public function scopeProcessID($query, $processID)
    {
        return $query->where('process_id', $processID);
    }

    public function scopeTaskID($query, $taskID)
    {
        return $query->where('task_id', $taskID);
    }
}
