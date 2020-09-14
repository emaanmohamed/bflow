<?php

namespace App\Http\Controllers\BusinessLogic;

use App\Task;

trait PaymentCaseHandler {

    public function CheckContactPerson($request, $_request, $usersArr)
    {
        $contactPerson = false; $nextUserID = false;

        if (isset($request->contact_user_id)
            && ! empty($request->contact_user_id)
            && ! is_null($request->contact_user_id)
        ) {
            $contactPerson = true; $nextUserID = $request->contact_user_id;
        }

        if ($contactPerson === true) {
            $task = Task::find(17);
            $_request['task_id'] = 17;
            $_request['task_name'] = $task->task_name;
            $_request['current_user_id'] = $nextUserID;
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
        }

        return $_request;
    }

    public function CheckIfCFOAssignUser($request, $_request, $usersArr)
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

}