<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\BeltoneBankList;
use App\BeltoneCase;
use App\BeltoneCompany;
use App\BeltoneCostCenter;
use App\BeltoneVendor;
use App\Comment;
use App\CurrencyRate;
use App\Http\Controllers\BusinessLogic\BeltoneCaseHandler;
use App\Http\Controllers\BusinessLogic\PurchaseCaseHandler;
use App\ItemDetails;
use App\Jobs\NotifyCompleteCaseJob;
use App\Jobs\NotifyUserJob;
use App\ParticipatedCase;
use App\CaseAttachment;
use App\BankTransaction;
use App\PurchaseBLOCostCenter;
use App\PurchaseCase;
use App\PurchaseCaseLog;
use App\CostControl;
use App\Task;
use App\TaskListUser;
use App\TempCaseData;
use App\Triats\NotificationTrait;
use App\User;
use App\VerifyListUser;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use stdClass;

class PurchaseCaseController extends Controller
{
    use NotificationTrait, PurchaseCaseHandler, BeltoneCaseHandler;

    protected $purchaseProcess;
    protected $lineManagerTaskID;
    protected $requesterID;

    public function __construct()
    {
        $this->purchaseProcess = env('PURCHASE_PROCESS');
        $this->lineManagerTaskID = 2;
        $this->requesterID = 1;
    }

    public function index()
    {
        $start = startStopWatch();
        $hasVerifyOption = 0; $verifyUsers = [];
        $checkAccess = true;

        if ($checkAccess === true) {
            $companies = BeltoneCompany::pluck('comp_name', 'comp_code');
            $vendors = BeltoneVendor::pluck('vendor_name');
            $caseNumber = BeltoneCase::create([
                'process_id' => $this->purchaseProcess,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id
            ]);

            $purchase = PurchaseCase::create([
                'id'    => $caseNumber->id,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'   => 1,
                'current_user_id'   => Auth::user()->id
            ]);

            PurchaseCaseLog::create([
                'case_id'    => $purchase->id,
                'status'    => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'   => 1,
                'current_user_id'   => Auth::user()->id
            ]);

            ParticipatedCase::create([
                'process_id'    => $this->purchaseProcess,
                'case_id'   => $purchase->id,
                'task_id'   => 1,
                'user_id'   => Auth::user()->id
            ]);

            $tasks = Task::where('id', 1)->active()->get();

            if (isset($tasks[0])) {
                $hasVerifyOption = $tasks[0]->has_verify_option;
                $formName = $tasks[0]->form_name;
            } else
                return \Redirect::Route('dashboard')->with([
                    'status'    => 'We cannot found form for task id: ' . 1 . ' or maybe inactive.',
                    'statusType'      => "danger"
                ]);

            if ($hasVerifyOption == 1)
                $verifyUsers = VerifyListUser::GetVerifyUsers($purchase->current_user_id);

            Log::channel('info')->info("Case: {$purchase->id} - (Create Request): " . StopStopWatch($start) . ' ms');

            $vendors = array_filter($vendors->toArray());
            return view('process_forms.purchase.master', compact('purchase', 'tasks',
                            'formName', 'vendors', 'companies', 'hasVerifyOption', 'verifyUsers'));
        }

        return \Redirect::Route('dashboard')->with([
            'status'    => "You don't has access to create Purchase Request",
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

    public function confirmSubmit(Request $request)
    {
        $start = startStopWatch();

        $checkCurrentUser = PurchaseCase::find($request->id);
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
                        'previous_user_name', 'current_user_name', 'task_name', 'request', 'caseAttachmentIds', 'function'];

        $this->UpdateCaseAttachmentWithCommentID($id, $data, $this->purchaseProcess);

        $participatedID = ParticipatedCase::create([
            'process_id'    => $this->purchaseProcess,
            'case_id'   => $id,
            'task_id'   => isset($data['task_id']) ? $data['task_id'] : $data['previous_task_id'],
            'user_id'   => isset($data['current_user_id']) ? $data['current_user_id'] : null,
            'previous_user_id'  => $data['previous_user_id'],
            'previous_task_id'  => $data['previous_task_id'],
        ]);

//        if (isset($data['function']))
//            $data['line_manager_approval'] = 1;

        if ($data['goToUserIDToVerify'] === false && $data['verifyTask'] === false) {
            PurchaseCase::findOrFail($id)->update(array_except($data, $exceptKeys));

            $data['case_id'] = $id;
            PurchaseCaseLog::create(array_except($data, $exceptKeys));
        }  elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false) {
            $data['verify_task'] = 0;
            PurchaseCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            PurchaseCaseLog::create(array_except($data, $exceptKeys));
        } else {
            $data['verify_task'] = 1;
            PurchaseCase::findOrFail($id)->update(array_except($data, $exceptKeys));
            $data['case_id'] = $id;
            PurchaseCaseLog::create(array_except($data, $exceptKeys));
        }

        if (isset($data['current_user_name']) && ! empty($data['current_user_name']))
            $statusMessage = "Your task has been sent to {$data['current_user_name']} ({$data['task_name']})";
        elseif ($data['goToUserIDToVerify'] !== false && $data['verifyTask'] === false)
            $statusMessage = "This case will go to selected user to verify it and then get back to you.";
        elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false)
            $statusMessage = "This case will go to sender person.";
        else
            $statusMessage = "This case has been Completed.";

        $purchase = PurchaseCase::find($id);

        BeltoneCase::find($id)->update([
            'title' => $purchase->title,
            'created_by_user_id' => $purchase->created_by_user_id,
            'current_user_id' => $purchase->current_user_id,
            'task_id' => $purchase->task_id,
            'status'  => $purchase->status,
            'previous_user_id'  => $data['previous_user_id'],
            'previous_task_id'  => $data['previous_task_id'],
            'read' => 0
        ]);

        if ($purchase->status == 'completed') {
            dispatch((new NotifyCompleteCaseJob($purchase, $this->purchaseProcess))->delay(Carbon::now()->addSeconds(3)));

            BeltoneCase::find($id)->update([
                'current_user_id' => $purchase->created_by_user_id,
                'status'  => 'completed',
                'read' => 0
            ]);
            PurchaseCase::findOrFail($id)->update(['current_user_id' => $purchase->created_by_user_id]);
            PurchaseCaseLog::create(['current_user_id' => $purchase->created_by_user_id]);
            ParticipatedCase::find($participatedID->id)->update([
                'user_id'   => $purchase->created_by_user_id,
            ]);
        } else {
            dispatch((new NotifyUserJob($purchase, $this->purchaseProcess))->delay(Carbon::now()->addSeconds(3)));
        }

        TempCaseData::where('case_id', $request->id)->delete();

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');
        return \Redirect::Route('dashboard')->with([
            'status'    => $statusMessage,
            'statusType'      => "success"
        ]);
    }

    public function reviewRequest($id)
    {
        $start = startStopWatch();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();

        if (isset($tempData->data))
            $_request = (array) json_decode($tempData->data);
        else
            return redirect()->route('dashboard');

        $purchase = PurchaseCase::findOrFail($id);

        if (Auth::user()->id != $purchase->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'    => 'You cannot access this case',
                'statusType'      => "danger"
            ]);
        }

        $itemDetails = ItemDetails::caseID($id)->get();
        $purchaseCostCenter = CostControl::caseID($id)->get();
        $purchaseBLOCostCenter = PurchaseBLOCostCenter::where('case_id', $id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();

        Log::channel('info')->info("Case: $id - (confirmSubmit function): " . StopStopWatch($start) . ' ms');

        $rate = CurrencyRate::ConvertToEGP(1, $purchase->invoice_currency);

        return view('process_forms.purchase.review_request_before_submit',
            compact('_request', 'purchase', 'itemDetails', 'purchaseCostCenter',
                'purchaseBankTransactions', 'purchaseBLOCostCenter', 'rate'));
    }

    public function store(Request $request, $id, $taskId)
    {
        $start = startStopWatch();
        $goToUserIDToVerify = false; $verifyTask = false; $skipLineManager = false;

        if (isset($request->verify_user_id) && ! empty($request->verify_user_id) && ! is_null($request->verify_user_id))
            $goToUserIDToVerify = $request->verify_user_id;

        $taskId = (int) $taskId;
        $tasksArr = Task::pluck('task_name', 'id');
        $usersArr = User::pluck('name', 'id');
        $logMSG = [];
        $_request = [];

        CostControl::CreateCostControlForPurchaseProcess($request->all(), $id);
        PurchaseBLOCostCenter::CreateBLOCostControlForCaseID($request->all(), $id);
        BankTransaction::CreateBankTransactionForPurchaseRequest($request->all(), $id);
        $attachments = $this->saveAttachments($request);
        $caseAttachmentIds = CaseAttachment::SaveAttachments($attachments, $id);

        $exceptKeys = ['_token', '_method', 'comment', 'price', 'quantity', 'total', 'description', 'attachments',
                'purchase_parent_company_code', 'purchase_sub_company_code', 'purchase_cost_center_code', 'purchase_distribution',
                'purchase_budget', 'purchase_as_contract', 'purchase_comment', 'finance_company_code', 'finance_company_code', 'finance_bank_code', 'finance_amount', 'finance_currency', 'blo_parent_company_code', 'blo_sub_company_code', 'blo_cost_center_code', 'blo_distribution',
            'blo_budget', 'blo_as_contract', 'blo_comment', 'cfo_approval_user_id', 'cfo_reject_task_id'];

        PurchaseCase::findOrFail($id)->update(array_except($request->all(), $exceptKeys));

        PurchaseCaseLog::create(array_except(array_merge($request->all(), ['case_id' => $id]), $exceptKeys));
        $case = PurchaseCase::findOrFail($id);

        if ($case->skip_line_manager == 1)
            $skipLineManager = true;

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

            $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($taskId, $this->purchaseProcess);

            if ($firstTask === true) {
                $amount = ItemDetails::CreateItemDetailsForCaseID($request, $id);

                $title = GetBaseTitleForCase($request, $id) . "[{$request->title}]";
                PurchaseCase::findOrFail($id)->update(['amount' => round($amount, 2), 'title' => $title]);
            }

            $getNextTaskWithUserID = Task::GetNextTaskWithUserID($taskId, $case, $request->all(), $this->purchaseProcess, $logMSG);

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

            $_request['task_name'] = $tasksArr[$_request['task_id']];
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
            $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
            $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];
        }

        $_request['case_id'] = $id;


        $_request['goToUserIDToVerify'] = $goToUserIDToVerify;
        $_request['verifyTask'] = $verifyTask;
        $_request['caseAttachmentIds'] = $caseAttachmentIds;

        $_request = $this->CFOApprovalUserID($request, $_request, $usersArr);
        $_request = $this->CFORejectReturnToTaskID($request, $_request, $tasksArr, $usersArr, $case);

        $_request['request'] = $request->all();

        $_request = $this->CheckIfLineManagerAndBLOSamePerson($_request, $usersArr, $tasksArr, $this->purchaseProcess);
        $_request = $this->CheckIfRequesterAndLineManagerSamePerson($_request, $usersArr, $tasksArr, $this->purchaseProcess);

        $PurchaseCaseStore = caseLog($case->id, '/logs/store-cases/');
        $PurchaseCaseStore->addinfo(json_encode($_request));

        if ($skipLineManager === true && isset($_request['task_id'])
            && $_request['task_id'] == $this->lineManagerTaskID) {
            $_request['task_id'] = $this->requesterID;
            $_request['task_name'] = $tasksArr[$_request['task_id']];
            $_request['current_user_id'] = $case->created_by_user_id;
            $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
        }

        TempCaseData::where('case_id', $id)->whereNull('type')->delete();
        TempCaseData::create([
           'case_id'    => $id, 'data'  => json_encode($_request)
        ]);

        if (is_null($_request['current_user_id']))
            return \Redirect::Route('dashboard')->with([
                'status'    => 'we cannot found line manager for this user: ' . Auth::user()->id,
                'statusType'      => "danger"
            ]);

        Log::channel('info')->info("Case: $id - (store function): " . StopStopWatch($start) . ' ms');
        return redirect()->route('review_request_before_submit', $id);
    }

    public function downloadPDF($id)
    {
        $purchase = PurchaseCase::findOrFail($id);
        $itemDetails = ItemDetails::caseId($id)->get();

        $data = [
            'purchase'  => $purchase,
            'itemDetails'   => $itemDetails
        ];

        $pdf = PDF::loadView('ajax.preview_case_pdf_version', $data);
        return $pdf->stream('previewCase.pdf');
    }

    public function getSubCompanies($id)
    {
        return response()->json(BeltoneCompany::GetSubCompaniesForCompanyID($id));
    }

    public function getCostCenter($companyCode)
    {
        return response()->json(BeltoneCostCenter::GetCodeCenterForCompanyCode($companyCode));
    }
}

