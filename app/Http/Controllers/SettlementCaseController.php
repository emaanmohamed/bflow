<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\BankTransaction;
use App\BeltoneCase;
use App\BeltoneCompany;
use App\CaseAttachment;
use App\Comment;
use App\CostControl;
use App\CurrencyRate;
use App\Http\Controllers\BusinessLogic\BeltoneCaseHandler;
use App\Http\Controllers\BusinessLogic\SettlementCaseHandler;
use App\Jobs\NotifyCompleteCaseJob;
use App\Jobs\NotifyUserJob;
use App\ParticipatedCase;
use App\SettlementCase;
use App\SettlementCaseLog;
use App\SettlementItem;
use App\Task;
use App\TempCaseData;
use App\Triats\NotificationTrait;
use App\User;
use App\VerifyListUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SettlementCaseController extends Controller
{
    use NotificationTrait, SettlementCaseHandler, BeltoneCaseHandler;

    protected $settlementProcess;
    protected $requesterID;

    public function __construct()
    {
        $this->settlementProcess = env('SETTLEMENT_PROCESS');
    }

    public function index()
    {
        $start = startStopWatch();
        $hasVerifyOption = 0;
        $verifyUsers = [];
        $checkAccess = true;
        $checkAccess = User::CheckProcessAccess($this->settlementProcess, Auth::user()->id);

        if ($checkAccess === true) {
            $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
            $caseNumber = BeltoneCase::create([
                'process_id' => $this->settlementProcess,
                'status' => 'Draft',
                'created_by_user_id' => Auth::user()->id
            ]);

            $firstTaskID = Task::GetFirstTaskIDForProcessID($this->settlementProcess);

            $settlement = SettlementCase::create([
                'id' => $caseNumber->id,
                'status' => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id' => $firstTaskID,
                'current_user_id' => Auth::user()->id
            ]);

            SettlementCaseLog::create([
                'case_id' => $settlement->id,
                'status' => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id' => $firstTaskID,
                'current_user_id' => Auth::user()->id
            ]);

            ParticipatedCase::create([
                'process_id' => $this->settlementProcess,
                'case_id' => $settlement->id,
                'task_id' => $firstTaskID,
                'user_id' => Auth::user()->id
            ]);

            $tasks = Task::where('id', $firstTaskID)->active()->get();

            if (isset($tasks[0])) {
                $hasVerifyOption = $tasks[0]->has_verify_option;
                $formName = $tasks[0]->form_name;
            } else
                return \Redirect::Route('dashboard')->with([
                    'status' => 'We cannot found form for task id: ' . $firstTaskID . ' or maybe inactive.',
                    'statusType' => "danger"
                ]);

            if ($hasVerifyOption == 1)
                $verifyUsers = VerifyListUser::GetVerifyUsers($settlement->current_user_id);

            Log::channel('info')
                ->info("Case: {$settlement->id} - (Create Request): " . StopStopWatch($start) . ' ms');

            return view('process_forms.settlement.master', compact('settlement', 'tasks',
                'formName', 'companies', 'hasVerifyOption', 'verifyUsers'));
        }

        return \Redirect::Route('dashboard')->with([
            'status'    => "You don't has access to create Settlement Request",
            'statusType'      => "danger"
        ]);
    }

    private function saveAttachments($request)
    {
        $data = [];
        if ($request->hasFile('attachments')) {
            if (is_array($request->attachments)) {
                $attachments = $request->attachments;
                for ($i = 0, $len = count($attachments); $i < $len; $i++) {
                    array_push($data, Attachment::saveSingleAttachment($attachments[$i]));
                }
            } else {
                return Attachment::saveSingleAttachment($request->attachments);
            }
        }

        return $data;
    }

    public function store(Request $request, $id, $taskId)
    {
        $start = startStopWatch();
        $goToUserIDToVerify = false; $verifyTask = false;

        if (isset($request->verify_user_id) && ! empty($request->verify_user_id) && ! is_null($request->verify_user_id))
            $goToUserIDToVerify = $request->verify_user_id;

        $taskId = (int) $taskId;
        $tasksArr = Task::pluck('task_name', 'id');
        $usersArr = User::pluck('name', 'id');
        $logMSG = [];
        $_request = [];

        CostControl::CreateCostControlForSettlementProcess($request->all(), $id);
        BankTransaction::CreateBankTransactionForSettlementRequest($request->all(), $id);
        $attachments = $this->saveAttachments($request);
        $caseAttachmentIds = CaseAttachment::SaveAttachments($attachments, $id);

        $exceptKeys = ['_token', '_method', 'comment', 'item_details_amount', 'item_details_currency', 'item_details_transaction_date', 'item_details_vendor', 'item_details_description',
            'item_details_case', 'cfo_approval_user_id', 'cfo_reject_task_id', 'purchase_parent_company_code', 'purchase_sub_company_code', 'purchase_cost_center_code', 'purchase_distribution',
            'purchase_budget', 'purchase_as_contract', 'purchase_comment', 'finance_company_code', 'finance_company_code', 'finance_bank_code', 'finance_amount', 'finance_currency'];

        SettlementCase::findOrFail($id)->update(array_except($request->all(), $exceptKeys));

        SettlementCaseLog::create(array_except(array_merge($request->all(), ['case_id' => $id]), $exceptKeys));
        $case = SettlementCase::findOrFail($id);

        BeltoneCase::find($id)->update([
            'title' => $case->title,
            'created_by_user_id' => $case->created_by_user_id,
            'current_user_id' => $case->current_user_id,
            'task_id' => $case->task_id,
            'status'  => $case->status
        ]);

        if ($case->verify_task == 1)
            $verifyTask = true;

        if ($goToUserIDToVerify === false && $verifyTask === false) {

            $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($taskId, $this->settlementProcess);

            if ($firstTask === true) {
                $amount = SettlementItem::CreateItemDetailsForCaseID($request, $id);

                $title = GetBaseTitleForCase($request, $id) . "[{$request->title}]";
                SettlementCase::findOrFail($id)->update(['amount' => round($amount, 2), 'title' => $title]);
            }

            $getNextTaskWithUserID = Task::GetNextTaskWithUserID($taskId, $case, $request->all(), $this->settlementProcess, $logMSG);
            $_request['current_user_id'] = is_null($getNextTaskWithUserID['nextUserID'])
                ? $getNextTaskWithUserID['nextUserIDFromNormalWorkFlow']
                : $getNextTaskWithUserID['nextUserID'];
            $_request['status'] = 'TO_DO';
            $_request['task_id'] = $getNextTaskWithUserID['nextTaskID'];
            $_request['previous_user_id'] = $getNextTaskWithUserID['previousUserID'];
            $_request['previous_task_id'] = $getNextTaskWithUserID['previousTaskID']
                ? $getNextTaskWithUserID['previousTaskID'] : false;

            if ($_request['task_id'] === false) {
                unset($_request['task_id']);
                $_request['status'] = 'completed';
            }

            if (isset($_request['task_id'])) {
                $_request['task_name'] = $tasksArr[$_request['task_id']];
            } else {
                $_request['task_name'] = 'Completed';
            }

            if (! is_null($_request['current_user_id']))
                $_request['current_user_name'] = $usersArr[$_request['current_user_id']];

            $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
            $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];

            $_request = $this->CFOApprovalUserID($request, $_request, $usersArr);
            $_request = $this->CFORejectReturnToTaskID($request, $_request, $tasksArr, $usersArr, $case);

        } elseif ($verifyTask === true && $goToUserIDToVerify === false) {
            $_request['task_id'] = $case->previous_task_id;
            $_request['current_user_id'] = $case->previous_user_id;
            $_request['previous_user_id'] = $case->current_user_id;
            $_request['previous_task_id'] = $taskId;

            $_request['task_name'] = $tasksArr[$_request['task_id']];
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
            $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
            $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];
        } else {
            $_request['task_id'] = Task::GetVerifyTaskID($taskId);
            $_request['current_user_id'] = (int) $goToUserIDToVerify;
            $_request['previous_user_id'] = $case->current_user_id;
            $_request['previous_task_id'] = $taskId;

            if ($_request['task_id'] === null)
                return \Redirect::Route('dashboard')->with([
                    'status'    => "Error: Task id => NULL - Settlement Request",
                    'statusType'      => "danger"
                ]);

            $_request['task_name'] = $tasksArr[$_request['task_id']];
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
            $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
            $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];
        }

        $_request['case_id'] = $id;
        $_request['goToUserIDToVerify'] = $goToUserIDToVerify;
        $_request['verifyTask'] = $verifyTask;
        $_request['caseAttachmentIds'] = $caseAttachmentIds;

        $_request['request'] = $request->all();

        TempCaseData::where('case_id', $id)->whereNull('type')->delete();
        TempCaseData::create([
            'case_id'    => $id, 'data'  => json_encode($_request)
        ]);

        Log::channel('info')->info("Case: $id - (store function): " . StopStopWatch($start) . ' ms');
        return redirect()->route('review_settlement_request_before_submit', $id);
    }

    public function reviewRequest($id)
    {
        $start = startStopWatch();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        $_request = (array) json_decode($tempData->data);
        $settlement = SettlementCase::findOrFail($id);
        $purchaseCostControl = CostControl::caseID($id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();

        if (Auth::user()->id != $settlement->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        $itemDetails = SettlementItem::caseID($id)->get();

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.settlement.review_settlement_request_before_submit',
            compact('_request', 'settlement', 'itemDetails', 'purchaseCostControl', 'purchaseBankTransactions'));
    }

    public function confirmSubmit(Request $request)
    {
        $start = startStopWatch();

        $checkCurrentUser = SettlementCase::find($request->id);
        if (Auth::user()->id != $checkCurrentUser->current_user_id) {
            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        $tempData = TempCaseData::where('case_id', $request->id)->whereNull('type')->first();

        if (is_null($tempData))
            return \Redirect::Route('dashboard')->with([
                'status'    => 'Cannot find Data for this case.
                                Please try to reopen the same case from inbox section and then submit before ask Support Team to handle it.',
                'statusType'      => "danger"
            ]);

        $data = (array) json_decode($tempData->data);
        $id = (int) $data['case_id'];

        $exceptKeys = ['goToUserIDToVerify', 'verifyTask', 'case_id', 'previous_task_name',
            'previous_user_name', 'current_user_name', 'task_name', 'request', 'caseAttachmentIds'];

        $this->UpdateCaseAttachmentWithCommentID($id, $data, $this->settlementProcess);

        $participatedID = ParticipatedCase::create([
            'process_id'    => $this->settlementProcess,
            'case_id'   => $id,
            'task_id'   => isset($data['task_id']) ? $data['task_id'] : $data['previous_task_id'],
            'user_id'   => isset($data['current_user_id']) ? $data['current_user_id'] : null,
            'previous_user_id'  => $data['previous_user_id'],
            'previous_task_id'  => $data['previous_task_id'],
        ]);

        if ($data['goToUserIDToVerify'] === false && $data['verifyTask'] === false) {
            SettlementCase::findOrFail($id)->update(array_except($data, $exceptKeys));

            $data['case_id'] = $id;
            SettlementCaseLog::create(array_except($data, $exceptKeys));
        }  elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false) {
            $data['verify_task'] = 0;
            SettlementCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            SettlementCaseLog::create(array_except($data, $exceptKeys));
        } else {
            $data['verify_task'] = 1;
            SettlementCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            SettlementCaseLog::create(array_except($data, $exceptKeys));
        }

        if (isset($data['current_user_name']) && ! empty($data['current_user_name']))
            $statusMessage = "Your task has been sent to {$data['current_user_name']} ({$data['task_name']})";
        elseif ($data['goToUserIDToVerify'] !== false && $data['verifyTask'] === false)
            $statusMessage = "This case will go to selected user to verify it and then get back to you.";
        elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false)
            $statusMessage = "This case will go to sender person.";
        else
            $statusMessage = "This case has been Completed.";

        $payment = SettlementCase::find($id);

        BeltoneCase::find($id)->update([
            'title' => $payment->title,
            'created_by_user_id' => $payment->created_by_user_id,
            'current_user_id' => $payment->current_user_id,
            'task_id' => $payment->task_id,
            'status'  => $payment->status,
            'previous_user_id'  => $data['previous_user_id'],
            'previous_task_id'  => $data['previous_task_id'],
            'read' => 0
        ]);

        if ($payment->status == 'completed') {

            dispatch((new NotifyCompleteCaseJob($payment, $this->settlementProcess))->delay(Carbon::now()->addSeconds(3)));

            BeltoneCase::find($id)->update([
                'current_user_id' => $payment->created_by_user_id,
                'status'  => 'completed',
                'read' => 0
            ]);
            SettlementCase::findOrFail($id)->update(['current_user_id' => $payment->created_by_user_id]);
            SettlementCaseLog::create(['current_user_id' => $payment->created_by_user_id]);

            ParticipatedCase::find($participatedID->id)->update([
                'user_id'   => $payment->created_by_user_id,
            ]);
        } else {
            dispatch((new NotifyUserJob($payment, $this->settlementProcess))->delay(Carbon::now()->addSeconds(3)));
        }

        TempCaseData::where('case_id', $request->id)->delete();

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');
        return \Redirect::Route('dashboard')->with([
            'status'    => $statusMessage,
            'statusType'      => "success"
        ]);
    }
}
