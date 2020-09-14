<?php

namespace App\Http\Controllers\BusinessLogic;

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
use App\Group;
use App\ItemDetails;
use App\Jobs\NotifyUserJob;
use App\ParticipatedCase;
use App\PaymentCase;
use App\PaymentCaseLog;
use App\BankTransaction;
use App\PurchaseBLOCostCenter;
use App\PurchaseCase;
use App\PurchaseCaseLog;
use App\Services\GuzzleService;
use App\SettlementCase;
use App\SettlementItem;
use App\Task;
use App\TaskListUser;
use App\TempCaseData;
use App\User;
use App\UsersActiveDirectory;
use App\VacationCase;
use App\VacationType;
use App\VerifyListUser;
use Carbon\Carbon;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait BeltoneCaseHandler {

    public $guzzleService;

    public function __construct(GuzzleService $guzzleService)
    {
        $this->guzzleService = $guzzleService;
    }


    private function previewPurchaseCase($request, $id)
    {
        $start = startStopWatch();
        $purchase = PurchaseCase::findOrFail($id);
        $itemDetails = ItemDetails::caseID($id)->get();
        $purchaseCostCenter = CostControl::caseID($id)->get();
        $purchaseBLOCostCenter = PurchaseBLOCostCenter::where('case_id', $id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();
        $attachments = CaseAttachment::case($id)->get();
        $participated = ParticipatedCase::caseID($id)->get();
        $rate = CurrencyRate::ConvertToEGP(1, $purchase->invoice_currency);

        if ($request->ajax()) {
            Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');
            return view('ajax.preview_case',
                compact('purchase', 'itemDetails', 'purchaseCostCenter',
                    'purchaseBankTransactions', 'purchaseBLOCostCenter', 'rate', 'attachments', 'participated'));
        }

        Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.purchase.preview_form',
            compact('purchase', 'itemDetails', 'purchaseCostCenter',
                'purchaseBankTransactions', 'purchaseBLOCostCenter', 'rate', 'attachments'));
    }

    private function previewSettlementCase($request, $id)
    {
        $start = startStopWatch();
        $settlement = SettlementCase::findOrFail($id);
        $itemDetails = SettlementItem::caseID($id)->get();
        $purchaseCostControl = CostControl::caseID($id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();
        $attachments = CaseAttachment::case($id)->get();
        $participated = ParticipatedCase::caseID($id)->get();

        if ($request->ajax()) {
            Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');
            return view('ajax.preview_settlement_case',
                compact('settlement', 'itemDetails', 'attachments', 'participated', 'purchaseCostControl',
                    'purchaseBankTransactions'));
        }

        Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');

        return view('ajax.preview_settlement_case',
            compact('settlement', 'itemDetails', 'attachments', 'participated', 'purchaseCostControl',
                'purchaseBankTransactions'));
    }

    private function previewPaymentCase($request, $id)
    {
        $start = startStopWatch();
        $payment = PaymentCase::findOrFail($id);
        $itemDetails = ItemDetails::caseID($id)->get();
        $paymentCostControl = CostControl::caseID($id)->get();
        $paymentBankTransactions = [];
        $attachments = CaseAttachment::case($id)->get();
        $participated = ParticipatedCase::caseID($id)->get();
        $rate = CurrencyRate::ConvertToEGP(1, $payment->invoice_currency);

        if ($request->ajax()) {
            Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');
            return view('ajax.preview_payment_case',
                compact('payment', 'itemDetails', 'paymentCostControl', 'paymentBankTransactions', 'rate',
                    'attachments', 'participated'));
        }

        Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.payment.preview_form',
            compact('payment', 'itemDetails', 'paymentCostControl',
                'paymentBankTransactions', 'rate', 'attachments'));
    }

    private function previewVacationCase($request, $id)
    {
        $start = startStopWatch();
        $vacation = VacationCase::findOrFail($id);
        $participated = ParticipatedCase::caseID($id)->get();

        if ($request->ajax()) {
            Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');
            return view('ajax.preview_vacation_case',
                compact('vacation', 'participated'));
        }

        Log::channel('info')->info("Case: $id - (previewCase function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.vacation.preview_form',
            compact('vacation', 'participated'));
    }

    private function openPurchaseCase($id)
    {
        $start = startStopWatch();
        $hasVerifyOption = 0; $verifyUsers = []; $cfoApproveList = []; $cfoRejectList = []; $currentComment = null;
        $purchase = PurchaseCase::findOrFail($id);

        if (Auth::user()->id != $purchase->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        BeltoneCase::find($id)->update([
            'read' => 1
        ]);

        $requestTasks = ParticipatedCase::select('task_id')->distinct("task_id")->caseID($id)->get();

        $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
        $costCenters = BeltoneCostCenter::orderBy('COSTCENTER_CODE')->get();
        $banks = BeltoneBankList::pluck('BANK_NAME', 'BANK_ID');
        $vendors = BeltoneVendor::pluck('vendor_name');
        $itemDetails = ItemDetails::caseID($id)->get();
        $purchaseCostCenter = CostControl::caseID($id)->get();
        $purchaseBLOCostCenter = PurchaseBLOCostCenter::where('case_id', $id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();

        $lastIndex = count($requestTasks);
        $lastTask = $requestTasks[--$lastIndex]->task_id;
        $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($purchase->task_id, env('PURCHASE_PROCESS'));

        $comments = Comment::case($id)->processID(env('PURCHASE_PROCESS'))->get();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        if ($tempData) {
            $_request = (array) json_decode($tempData->data);
            $currentComment = $_request['request']->comment;
        }
        $attachments = CaseAttachment::case($id)->get();

        $verifyUsers = VerifyListUser::GetVerifyUsers($purchase->current_user_id);

        if ($purchase->task_id == 5) {
            $cfoApproveList = TaskListUser::GetTaskListUsers($purchase->task_id);
            $cfoRejectList = ParticipatedCase::CFORejectList(env('PURCHASE_PROCESS'), $purchase->id, $purchase->task_id);
        }
        $vendors = array_filter($vendors->toArray());
        Log::channel('info')->info("Case: $id - (openRequest function): " . StopStopWatch($start) . ' ms');

        $rate = CurrencyRate::ConvertToEGP(1, $purchase->invoice_currency);

        return view('process_forms.purchase.master', compact('purchase', 'requestTasks',
            'vendors', 'companies', 'itemDetails', 'lastTask', 'firstTask',
            'comments', 'attachments', 'verifyUsers', 'banks', 'costCenters',
            'purchaseCostCenter', 'purchaseBankTransactions', 'purchaseBLOCostCenter',
            'currentComment', 'cfoApproveList', 'cfoRejectList', 'rate'));
    }

    private function openPaymentCase($id, $processID)
    {
        $start = startStopWatch();
        $hasVerifyOption = 0; $verifyUsers = []; $cfoApproveList = []; $cfoRejectList = []; $currentComment = null;
        $payment = PaymentCase::findOrFail($id);

        if (Auth::user()->id != $payment->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        BeltoneCase::find($id)->update([
            'read' => 1
        ]);

        $requestTasks = ParticipatedCase::select('task_id')->distinct("task_id")->caseID($id)->get();
        $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
        $costCenters = BeltoneCostCenter::orderBy('COSTCENTER_CODE')->get();
        $banks = BeltoneBankList::pluck('BANK_NAME', 'BANK_ID');
        $vendors = BeltoneVendor::pluck('vendor_name', 'vendor_code');
        $itemDetails = ItemDetails::caseID($id)->get();
        $paymentCostControl = CostControl::caseID($id)->get();
        $paymentBankTransactions = [];

        $lastIndex = count($requestTasks);
        $lastTask = $requestTasks[--$lastIndex]->task_id;
        $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($payment->task_id, $processID);

        $comments = Comment::case($id)->processID($processID)->get();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        if ($tempData) {
            $_request = (array) json_decode($tempData->data);
            $currentComment = $_request['request']->comment;
        }
        $attachments = CaseAttachment::case($id)->get();

        $verifyUsers = VerifyListUser::GetVerifyUsers($payment->current_user_id);

        if ($payment->task_id == 18) {
            $cfoApproveList = TaskListUser::GetTaskListUsers($payment->task_id);
        }

        $contactUsers = ContactPersonListUser::GetContactPersonList($payment->current_user_id);

        Log::channel('info')->info("Case: $id - (openRequest function): " . StopStopWatch($start) . ' ms');
        $vendors = array_filter($vendors->toArray());

        $rate = CurrencyRate::ConvertToEGP(1, $payment->invoice_currency);

        return view('process_forms.payment.master', compact('payment', 'requestTasks',
            'vendors', 'companies', 'itemDetails', 'lastTask', 'firstTask',
            'comments', 'attachments', 'verifyUsers', 'banks', 'costCenters',
            'paymentCostControl', 'paymentBankTransactions',
            'currentComment', 'cfoApproveList', 'cfoRejectList', 'contactUsers', 'rate'));
    }

    private function openSettlementCase($id, $processID)
    {
        $start = startStopWatch();
        $hasVerifyOption = 0; $verifyUsers = []; $cfoApproveList = []; $cfoRejectList = []; $currentComment = null;
        $settlement = SettlementCase::findOrFail($id);

        if (Auth::user()->id != $settlement->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        BeltoneCase::find($id)->update([
            'read' => 1
        ]);

        $requestTasks = ParticipatedCase::select('task_id')->distinct("task_id")->caseID($id)->get();
        $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
        $costCenters = BeltoneCostCenter::orderBy('COSTCENTER_CODE')->get();
        $purchaseCostControl = CostControl::caseID($id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();
        $banks = BeltoneBankList::pluck('BANK_NAME', 'BANK_ID');
        $itemDetails = SettlementItem::caseID($id)->get();

        $lastIndex = count($requestTasks);
        $lastTask = $requestTasks[--$lastIndex]->task_id;
        $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($settlement->task_id, $processID);

        $comments = Comment::case($id)->processID($processID)->get();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        if ($tempData) {
            $_request = (array) json_decode($tempData->data);
            $currentComment = $_request['request']->comment;
        }
        $attachments = CaseAttachment::case($id)->get();

        $verifyUsers = VerifyListUser::GetVerifyUsers($settlement->current_user_id);


        if ($settlement->task_id == 24) {
            $cfoApproveList = TaskListUser::GetTaskListUsers($settlement->task_id);
            $cfoRejectList = ParticipatedCase::CFORejectList(env('SETTLEMENT_PROCESS'), $settlement->id, $settlement->task_id);
        }

        Log::channel('info')->info("Case: $id - (openRequest function): " . StopStopWatch($start) . ' ms');

        return view('process_forms.settlement.master', compact('settlement', 'requestTasks',
            'companies', 'itemDetails', 'lastTask', 'firstTask',
            'comments', 'attachments', 'verifyUsers', 'banks', 'costCenters',
            'currentComment', 'cfoApproveList', 'cfoRejectList', 'purchaseCostControl', 'purchaseBankTransactions'));
    }
    private function openVacationCase($id, $processID)
    {
        $start = startStopWatch();
        $currentComment = null;
        $vacation = VacationCase::findOrFail($id);

        if (Auth::user()->id != $vacation->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'       => 'You cannot access this case',
                'statusType'   => "danger"
            ]);
        }

        BeltoneCase::find($id)->update([
            'read' => 1
        ]);

        $requestTasks = ParticipatedCase::select('task_id')->distinct("task_id")->caseID($id)->get();
        $vacations = VacationType::all();
        $itemDetails = VacationCase::caseID($id)->get();

        $lastIndex = count($requestTasks);
        $lastTask = $requestTasks[--$lastIndex]->task_id;
        $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($vacation->task_id, $processID);

        $comments = Comment::case($id)->processID($processID)->get();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        if ($tempData) {
            $_request = (array) json_decode($tempData->data);
            $currentComment = $_request['request']->comment;
        }

        $employeeEmail = User::select('email')->where('id', $vacation->created_by_user_id)->get();
        $params = [
            "email" => $employeeEmail[0]->email
        ];
        $response = $this->guzzleService->post(env('VACATION_BALANCE_API') . "getAdUser", [
            RequestOptions::JSON => $params
        ]);
    
             if (isset($response->employeeId)) {
                  $employeeID = $response->employeeId;
             } else {
             	return redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);
              }
             
        $response = $this->guzzleService->get(env('VACATION_BALANCE_API') . "getvacationbalance/{$employeeID}");
        if ($response->result == true) {
            $balanceData = $response->vacationsBalance[0];

            Log::channel('info')->info("Case: $id - (openRequest function): ".StopStopWatch($start).' ms');

            return view('process_forms.vacation.master', compact('vacation', 'requestTasks', 'itemDetails', 'lastTask', 'firstTask',
                'comments', 'currentComment', 'balanceData', 'vacations'));
        } else {
            return view('process_forms.vacation.master', compact('vacation', 'requestTasks', 'itemDetails', 'lastTask', 'firstTask',
                'comments', 'currentComment', 'vacations'));
        }
    }

    private function assignUserForPurchaseCase($request, $caseID)
    {
        PurchaseCase::find($caseID)->update([
            'current_user_id' => $request['user_id']
        ]);

        $purchase = PurchaseCase::find($caseID);

        dispatch((new NotifyUserJob($purchase, env('PURCHASE_PROCESS')))->delay(Carbon::now()->addSeconds(3)));
    }

    private function assignUserForPaymentCase($request, $caseID)
    {
        PaymentCase::find($caseID)->update([
            'current_user_id' => $request['user_id']
        ]);

        $payment = PaymentCase::find($caseID);

        dispatch((new NotifyUserJob($payment, env('PAYMENT_PROCESS')))->delay(Carbon::now()->addSeconds(3)));
    }

    private function assignUserForSettlementCase($request, $caseID)
    {
        SettlementCase::find($caseID)->update([
            'current_user_id' => $request['user_id']
        ]);

        $settlement = SettlementCase::find($caseID);

        dispatch((new NotifyUserJob($settlement, env('SETTLEMENT_PROCESS')))->delay(Carbon::now()->addSeconds(3)));
    }

    private function CheckIfLineManagerAndBLOSamePerson($request, $usersArr, $tasksArr, $processID)
    {
        $companyID = isset($request['request']['company_id']) ? $request['request']['company_id'] : null;

        if (is_null($companyID))
            return $request;

        $bloUserID = BeltoneCompany::GetBLOForCompanyID($companyID, true);
        $case = PurchaseCase::find($request['case_id']);

        if ($request['current_user_id'] == $bloUserID) {
            $result = Task::GetNextTaskWithUserIDToSkip($request, $case, $processID);
            $request['task_id'] = (int) $result['nextTaskID'];
            $request['task_name'] = $tasksArr[$result['nextTaskID']];
            $request['current_user_id'] = $result['nextUserID'];
            $request['current_user_name'] = $usersArr[$result['nextUserID']];
            $request['previous_user_id'] = $result['previousUserID'];
            $request['previous_task_id'] = $result['previousTaskID'];
            $request['previous_task_id'] = $result['previousTaskID'];
            $request['skip_line_manager'] = 1;
            $request['function'] = ['functionName' => 'CheckIfLineManagerAndBLOSamePerson', 'result' => $result];
        }

        return $request;
    }



    private function CheckIfRequesterAndLineManagerSamePerson($request, $usersArr, $tasksArr, $processID)
    {

        $case = PurchaseCase::find($request['case_id']);

        $isLineManager = Group::managerID($case->current_user_id)->first();

        if ($case->current_user_id == $case->created_by_user_id && $isLineManager) {
            $result = Task::GetNextTaskWithUserIDToSkip($request, $case, $processID);
            $request['task_id'] = (int) $result['nextTaskID'];
            $request['task_name'] = $tasksArr[$result['nextTaskID']];
            $request['current_user_id'] = $result['nextUserID'];
            $request['current_user_name'] = $usersArr[$result['nextUserID']];
            $request['previous_user_id'] = $result['previousUserID'];
            $request['previous_task_id'] = $result['previousTaskID'];
            $request['previous_task_id'] = $result['previousTaskID'];
            $request['skip_line_manager'] = 1;
            $request['function'] = ['functionName' => 'CheckIfRequesterAndLineManagerSamePerson', 'result' => $result];
        }

        return $request;
    }

    private function UpdateCaseAttachmentWithCommentID($id, $data, $processID)
    {
        $participated = ParticipatedCase::caseID($id)->latest()->first();

        $comment = Comment::CreateCommentForCaseID($data['request'], $id, $data['previous_task_id'],
                                    $processID, $participated->id);

        if (isset($data['caseAttachmentIds']) && count($data['caseAttachmentIds'])) {
            for ($i = 0, $len = count($data['caseAttachmentIds']); $i < $len; $i++) {
                CaseAttachment::find($data['caseAttachmentIds'][$i])->update([
                    'comment_id' => isset($comment->id) ? $comment->id : null
                ]);
            }
        }

        $attachmentsIDs = TempCaseData::where('type', 'ajax')->where('case_id', $id)->get();

        for ($i = 0, $len = count($attachmentsIDs); $i < $len; $i++) {
            $attachmentsIDsData = (array) json_decode($attachmentsIDs[$i]->data);

            if (isset($attachmentsIDsData) && count($attachmentsIDsData)) {

                for ($i = 0, $len = count($attachmentsIDsData); $i < $len; $i++) {
                    if (! is_null($attachmentsIDsData[$i])) {
                        $caseAttachment = CaseAttachment::find($attachmentsIDsData[$i]);

                        if ($caseAttachment)
                            $caseAttachment->update([
                                'comment_id' => isset($comment->id) ? $comment->id : null
                            ]);
                    }

                }
            }
        }
    }
}
