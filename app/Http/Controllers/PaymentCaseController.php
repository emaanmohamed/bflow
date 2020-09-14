<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\BeltoneBankList;
use App\BeltoneCase;
use App\BeltoneCompany;
use App\BeltoneCostCenter;
use App\BeltoneVendor;
use App\CaseAttachment;
use App\Comment;
use App\ContactPersonListUser;
use App\CostControl;
use App\CurrencyRate;
use App\Http\Controllers\BusinessLogic\BeltoneCaseHandler;
use App\Http\Controllers\BusinessLogic\PaymentCaseHandler;
use App\ItemDetails;
use App\Jobs\NotifyCompleteCaseJob;
use App\Jobs\NotifyUserJob;
use App\ParticipatedCase;
use App\PaymentCase;
use App\PaymentCaseLog;
use App\Task;
use App\TaskListUser;
use App\TempCaseData;
use App\User;
use App\VerifyListUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentCaseController extends Controller
{
    use PaymentCaseHandler, BeltoneCaseHandler;

    protected $paymentProcess;

    public function __construct()
    {
        $this->paymentProcess = env('PAYMENT_PROCESS');
    }

    public function index()
    {
        $start = startStopWatch();
        $hasVerifyOption = 0; $verifyUsers = [];
        $checkAccess = User::CheckProcessAccess($this->paymentProcess, Auth::user()->id);

        if ($checkAccess === true) {
            $tasks = Task::process($this->paymentProcess)->hasOrder(1)->active()->first();
            $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
            $vendors = BeltoneVendor::pluck('vendor_name');
            $caseNumber = BeltoneCase::create([
                'process_id' => $this->paymentProcess,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id
            ]);
            $costCenters = BeltoneCostCenter::orderBy('COSTCENTER_CODE')->get();

            $payment = PaymentCase::create([
                'id'    => $caseNumber->id,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'   => $tasks->id,
                'current_user_id'   => Auth::user()->id
            ]);

            PaymentCaseLog::create([
                'case_id'    => $payment->id,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'   => $tasks->id,
                'current_user_id'   => Auth::user()->id
            ]);

            ParticipatedCase::create([
                'process_id'    => $this->paymentProcess,
                'case_id'   => $payment->id,
                'task_id'   => $tasks->id,
                'user_id'   => Auth::user()->id
            ]);

            if ($tasks) {
                $hasVerifyOption = $tasks->has_verify_option;
                $formName = $tasks->form_name;
            } else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'We cannot found form for task id: ' . $tasks->id . ' or maybe inactive.',
                    'statusType'      => "danger"
                ]);

            if ($hasVerifyOption == 1)
                $verifyUsers = VerifyListUser::GetVerifyUsers($payment->current_user_id);

            $contactUsers = ContactPersonListUser::GetContactPersonList($payment->current_user_id);
            Log::channel('info')->info("Case: {$payment->id} - (Create Request): " . StopStopWatch($start) . ' ms');

            $vendors = array_filter($vendors->toArray());
            return view('process_forms.payment.master', compact('payment', 'tasks',
                'formName', 'vendors', 'companies', 'hasVerifyOption', 'verifyUsers', 'costCenters', 'contactUsers'));
        }

        return \Redirect::Route('dashboard')->with([
            'status'    => "You don't has access to create Payment Request",
            'statusType'      => "danger"
        ]);
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

        CostControl::CreateCostControlForPaymentProcess($request->all(), (int) $id);
        $attachments = $this->saveAttachments($request);
        $caseAttachmentIds = CaseAttachment::SaveAttachments($attachments, $id);

        $exceptKeys = ['_token', '_method', 'comment', 'price', 'quantity', 'total', 'description', 'attachments',
            'payment_parent_company_code', 'payment_sub_company_code', 'payment_cost_center_code', 'payment_distribution',
            'payment_budget', 'payment_as_contract', 'payment_comment', 'finance_company_code', 'finance_company_code', 'finance_bank_code', 'finance_amount', 'finance_currency', 'cfo_approval_user_id', 'cfo_reject_task_id'];

        PaymentCase::findOrFail($id)->update(array_except($request->all(), $exceptKeys));

        PaymentCaseLog::create(array_except(array_merge($request->all(), ['case_id' => $id]), $exceptKeys));
        $case = PaymentCase::findOrFail($id);

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

            $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($taskId, $this->paymentProcess);

            if ($firstTask === true) {
                $amount = ItemDetails::CreateItemDetailsForCaseID($request, $id);

                $title = GetBaseTitleForCase($request, $id) . "[{$request->title}]";
                PaymentCase::findOrFail($id)->update(['amount' => round($amount, 2), 'title' => $title]);
            }

            $case = PaymentCase::findOrFail($id);
            $getNextTaskWithUserID = Task::GetNextTaskWithUserID($taskId, $case, $request->all(), $this->paymentProcess, $logMSG);

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

            $_request = $this->CheckContactPerson($request, $_request, $usersArr);
            $_request = $this->CheckIfCFOAssignUser($request, $_request, $usersArr);

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
                    'status'    => "Error: Task id => NULL - Payment Request",
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
        return redirect()->route('review_payment_request_before_submit', $id);
    }

    public function reviewRequest($id)
    {
        $start = startStopWatch();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        $_request = (array) json_decode($tempData->data);
        $payment = PaymentCase::findOrFail($id);

        if (Auth::user()->id != $payment->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        $itemDetails = ItemDetails::caseID($id)->get();
        $paymentCostControl = CostControl::caseID($id)->get();
        $paymentBankTransactions = [];

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');

        $rate = CurrencyRate::ConvertToEGP(1, $payment->invoice_currency);

        return view('process_forms.payment.review_payment_request_before_submit',
            compact('_request', 'payment', 'itemDetails', 'paymentCostControl',
                'paymentBankTransactions', 'rate'));
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

    public function confirmSubmit(Request $request)
    {
        $start = startStopWatch();

        $checkCurrentUser = PaymentCase::find($request->id);
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

        $this->UpdateCaseAttachmentWithCommentID($id, $data, $this->paymentProcess);

        $participatedID = ParticipatedCase::create([
            'process_id'    => $this->paymentProcess,
            'case_id'   => $id,
            'task_id'   => isset($data['task_id']) ? $data['task_id'] : $data['previous_task_id'],
            'user_id'   => isset($data['current_user_id']) ? $data['current_user_id'] : null,
            'previous_user_id'  => $data['previous_user_id'],
            'previous_task_id'  => $data['previous_task_id'],
        ]);

        if ($data['goToUserIDToVerify'] === false && $data['verifyTask'] === false) {
            PaymentCase::findOrFail($id)->update(array_except($data, $exceptKeys));

            $data['case_id'] = $id;
            PaymentCaseLog::create(array_except($data, $exceptKeys));
        }  elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false) {
            $data['verify_task'] = 0;
            PaymentCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            PaymentCaseLog::create(array_except($data, $exceptKeys));
        } else {
            $data['verify_task'] = 1;
            PaymentCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            PaymentCaseLog::create(array_except($data, $exceptKeys));
        }

        if (isset($data['current_user_name']) && ! empty($data['current_user_name']))
            $statusMessage = "Your task has been sent to {$data['current_user_name']} ({$data['task_name']})";
        elseif ($data['goToUserIDToVerify'] !== false && $data['verifyTask'] === false)
            $statusMessage = "This case will go to selected user to verify it and then get back to you.";
        elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false)
            $statusMessage = "This case will go to sender person.";
        else
            $statusMessage = "This case has been Completed.";

        $payment = PaymentCase::find($id);

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

            dispatch((new NotifyCompleteCaseJob($payment, $this->paymentProcess))->delay(Carbon::now()->addSeconds(3)));

            BeltoneCase::find($id)->update([
                'current_user_id' => $payment->created_by_user_id,
                'status'  => 'completed',
                'read' => 0
            ]);
            PaymentCase::findOrFail($id)->update(['current_user_id' => $payment->created_by_user_id]);
            PaymentCaseLog::create(['current_user_id' => $payment->created_by_user_id]);

            ParticipatedCase::find($participatedID->id)->update([
                'user_id'   => $payment->created_by_user_id,
            ]);
        } else {
            dispatch((new NotifyUserJob($payment, $this->paymentProcess))->delay(Carbon::now()->addSeconds(3)));
        }

        TempCaseData::where('case_id', $request->id)->delete();

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');
        return \Redirect::Route('dashboard')->with([
            'status'    => $statusMessage,
            'statusType'      => "success"
        ]);
    }


    public function previewCase(Request $request, $id)
    {
        $start = startStopWatch();
        $payment = PaymentCase::findOrFail($id);
        $itemDetails = ItemDetails::caseID($id)->get();
        $paymentCostControl = CostControl::caseID($id)->get();
        $paymentBankTransactions = [];

        if ($request->ajax()) {
            Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');
            return view('ajax.preview_case',
                compact('payment', 'itemDetails', 'paymentCostControl', 'paymentBankTransactions'));
        }

        Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.payment.preview_form',
            compact('payment', 'itemDetails', 'paymentCostControl', 'paymentBankTransactions'));
    }
}
