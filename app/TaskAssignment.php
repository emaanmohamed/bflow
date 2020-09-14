<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskAssignment extends Model
{
    protected $table = 'task_assignment';
    protected $guarded = [];

    public function scopeTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function _task()
    {
        return $this->belongsTo("App\Task", "task_id");
    }

    public function _group()
    {
        return $this->belongsTo("App\Group", "group_id");
    }

    public static function GetUserIDForTaskID($taskID, $case, $logMSG = null)
    {
            if (is_null($taskID) || empty($taskID)) {
                $taskID = $case->task_id;
            }

            $groupID = TaskAssignment::task($taskID)->first();

            if (is_null($groupID)) {
                return \Redirect::Route('dashboard')->with([
                    'status'     => "There is no users has been assigned into this group yet.
                        Check TaskAssignment (Task ID: {$taskID})",
                    'statusType' => "danger"
                ]);
            }

            return GroupUser::getManagerForGroupID($groupID, $case, $logMSG['GetUserIDForTaskID'] = null);

    }
}
