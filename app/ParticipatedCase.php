<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ParticipatedCase extends Model
{
    protected $table = 'participated_case';
    protected $guarded = [];
    public $timestamps = false;

    public function scopeProcessID($query, $processID)
    {
        return $query->where('process_id', $processID);
    }

    public function scopeTaskID($query, $taskID)
    {
        return $query->where('task_id', $taskID);
    }

    public function scopeCaseID($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public function scopeUserID($query, $userID)
    {
        return $query->where('user_id', $userID);
    }

    public function task()
    {
        return $this->belongsTo("App\Task", "task_id");
    }

    public function comment()
    {
        return $this->hasOne("App\Comment", "participated_id", 'id');
    }

    public function case()
    {
        return $this->belongsTo("App\BeltoneCase", "case_id");
    }

    public function previousTask()
    {
        return $this->belongsTo("App\Task", "previous_task_id");
    }

    public function user()
    {
        return $this->belongsTo("App\User", "user_id");
    }

    public function previousUser()
    {
        return $this->belongsTo("App\User", "previous_user_id");
    }

    public static function GetParticipatedUserIDForTaskID($processID, $case, $taskID)
    {
        // get count > 1 then this mean (reassign)
        $participated = ParticipatedCase::processID($processID)->taskID($taskID)->caseID($case->id)->first();

        if (isset($participated->user_id) && ! is_null($participated->user_id)) {
            return $participated->user_id;
        } else
            return TaskAssignment::GetUserIDForTaskID($taskID, $case);
    }

    public static function CFORejectList($processID, $caseID, $taskID)
    {
        $data = ParticipatedCase::processID($processID)->caseID($caseID)->get();
        $list = [];

        for ($i = 0, $len = count($data); $i < $len; $i++) {
            if ($data[$i]->task_id < $taskID && $data[$i]->task_id != 23)
                array_push($list, $data[$i]->task_id);
        }

        return Task::whereIn('id', $list)->get();
    }
}
