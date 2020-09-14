<?php

namespace App\Http\Controllers;

use App\BeltoneBankList;
use App\BeltoneCase;
use App\BeltoneCompany;
use App\BeltoneCostCenter;
use App\BeltoneVendor;
use App\CaseAttachment;
use App\Comment;
use App\CostControl;
use App\Http\Controllers\BusinessLogic\BeltoneCaseHandler;
use App\ItemDetails;
use App\ParticipatedCase;
use App\BankTransaction;
use App\PurchaseBLOCostCenter;
use App\PurchaseCase;
use App\Task;
use App\TaskListUser;
use App\TempCaseData;
use App\User;
use App\VerifyListUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    use BeltoneCaseHandler;

    public function getReassginForm($id)
    {
        $users = User::active()->get();
        return view('ajax.reassign_content', compact('users', 'id'));
    }

    public function assignUser(Request $request)
    {
        $case = BeltoneCase::find($request->case_id);
        $caseID = $case->id;
        $processID = $case->process_id;
        $taskID = $case->task_id;

        $case->current_user_id = $request->user_id;
        $case->update();

        ParticipatedCase::create([
            'process_id'    => $processID,
            'case_id'   => $caseID,
            'task_id'   => $taskID,
            'user_id'   => $request->user_id,
            'previous_user_id'  => $case->previous_user_id,
            'previous_task_id'  => $case->previous_task_id,
        ]);

        Comment::AddNote($request->comment, $request->case_id, $processID);

        if ($case->process_id == env('PURCHASE_PROCESS'))
            $this->assignUserForPurchaseCase($request->all(), $caseID);
        else if ($case->process_id == env('PAYMENT_PROCESS'))
            $this->assignUserForPaymentCase($request->all(), $caseID);
        else if ($case->process_id == env('SETTLEMENT_PROCESS'))
            $this->assignUserForSettlementCase($request->all(), $caseID);

        BeltoneCase::find($caseID)->update([
            'read' => 0
        ]);

        return response()->json([
            'type'  => 'success', 'message' => 'Reassign Done', 'title' => 'Reassign'
        ]);
    }

    public function previewCase(Request $request, $id)
    {
        $process = BeltoneCase::find($id);
        if ($process->process_id == env('PURCHASE_PROCESS'))
            return $this->previewPurchaseCase($request, $id);
        else if ($process->process_id == env('PAYMENT_PROCESS'))
            return $this->previewPaymentCase($request, $id);
        else if ($process->process_id == env('SETTLEMENT_PROCESS'))
            return $this->previewSettlementCase($request, $id);
        else if ($process->process_id == env('VACATION_PROCESS'))
            return $this->previewVacationCase($request, $id);

        return 'previewCase';
    }

    public function openRequest($id)
    {
        $process = BeltoneCase::find($id);

        if ($process->process_id == env('PURCHASE_PROCESS'))
            return $this->openPurchaseCase($id);
        else if ($process->process_id == env('PAYMENT_PROCESS'))
            return $this->openPaymentCase($id, $process->process_id);
        else if ($process->process_id == env('SETTLEMENT_PROCESS'))
            return $this->openSettlementCase($id, $process->process_id);
        else if ($process->process_id == env('VACATION_PROCESS'))
            return $this->openVacationCase($id, $process->process_id);
    }

    public function getCases()
    {
        $cases = Beltonecase::getTODOCases()->orderBy('id', 'desc')->get();

        return view('admin.cases.index', compact('cases'));
    }

    public function ajaxCases()
    {
        $cases = Beltonecase::getTODOCases()->orderBy('id', 'desc')->get();

        return view('admin.cases.ajax.display_all_cases', compact('cases'));
    }
}

