<?php

namespace App\Http\Controllers\BusinessLogic;

use App\ParticipatedCase;

trait PurchaseCaseHandler {

    public function CFOApprovalUserID($request, $_request, $usersArr)
    {
        $goToCFOApproval = false; $nextUserCFOApproval = false;

        if (isset($request->cfo_approval_user_id)
            && ! empty($request->cfo_approval_user_id)
            && ! is_null($request->cfo_approval_user_id)
        ) {
            $goToCFOApproval = true; $nextUserCFOApproval = $request->cfo_approval_user_id;
        }

        if ($goToCFOApproval === true) {
            $_request['current_user_id'] = $nextUserCFOApproval;
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
        }

        return $_request;
    }

    public function CFORejectReturnToTaskID($request, $_request, $tasksArr, $usersArr, $case)
    {
        $CFOReject = false; $nextTaskIDCFOReject = false;

        if (isset($request->cfo_reject_task_id)
            && ! empty($request->cfo_reject_task_id)
            && ! is_null($request->cfo_reject_task_id)
        ) {
            $CFOReject = true; $nextTaskIDCFOReject = $request->cfo_reject_task_id;
        }

        if ($CFOReject === true) {
            $userID = ParticipatedCase::GetParticipatedUserIDForTaskID(env('PURCHASE_PROCESS'), $case, $nextTaskIDCFOReject);
            $_request['current_user_id'] = $userID;
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
            $_request['task_id'] = $nextTaskIDCFOReject;
            $_request['task_name'] = $tasksArr[$_request['task_id']];
        }

        return $_request;
    }
}