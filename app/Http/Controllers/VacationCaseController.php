<?php

namespace App\Http\Controllers;

use App\BeltoneCase;
use App\Comment;
use App\Jobs\NotifyCompleteCaseJob;
use App\Jobs\NotifyUserJob;
use App\ParticipatedCase;
use App\Services\GuzzleService;
use App\Task;
use App\TempCaseData;
use App\User;
use App\VacationCase;
use App\VacationCaseLog;
use App\VacationType;
use App\VerifyListUser;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Http\Client\Exception\RequestException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationCaseController extends Controller
{
    protected $vacationProcess;
    private $guzzleService;

    public function __construct(GuzzleService $guzzleService)
    {
        $this->vacationProcess = env('VACATION_PROCESS');
        $this->guzzleService = $guzzleService;
    }

    public function index()
    {
        $start       = startStopWatch();
        $verifyUsers = [];
        $checkAccess = true;

        if ($checkAccess === true) {

            $firstTaskID = Task::GetFirstTaskIDForProcessID($this->vacationProcess);

            $caseNumber = BeltoneCase::create([
                'process_id'         => $this->vacationProcess,
                'status'             => 'Draft',
                'created_by_user_id' => Auth::user()->id
            ]);

            $vacation = VacationCase::create([
                'id'                 => $caseNumber->id,
                'status'             => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'            => $firstTaskID,
                'current_user_id'    => Auth::user()->id
            ]);

            VacationCaseLog::create([
                'case_id'            => $vacation->id,
                'status'             => 'Draft',
                'created_by_user_id' => Auth::user()->id,
                'task_id'            => $firstTaskID,
                'current_user_id'    => Auth::user()->id
            ]);

            ParticipatedCase::create([
                'process_id' => $this->vacationProcess,
                'case_id'    => $vacation->id,
                'task_id'    => $firstTaskID,
                'user_id'    => Auth::user()->id
            ]);

            $tasks = Task::where('id', $firstTaskID)->active()->get();
            $vacations = VacationType::all();

            if (isset($tasks[0])) {
                $formName = $tasks[0]->form_name;
            } else {
                return \Redirect::Route('dashboard')->with([
                    'status'     => 'We cannot found form for task id: '.$firstTaskID.' or maybe inactive.',
                    'statusType' => "danger"
                ]);
            }

            Log::channel('info')
                ->info("Case: {$vacation->id} - (Create Request): ".StopStopWatch($start).' ms');

            $userEmail = User::select('email')->where('id', Auth::user()->id)->get();
           $params = [
               "email" => $userEmail[0]->email
           ];

            $responseGetUser = $this->guzzleService->post(env('VACATION_BALANCE_API') . "getAdUser", [
                RequestOptions::JSON => $params
            ]);
	            if (isset($responseGetUser->employeeId) && !empty($responseGetUser->employeeId)) {
		            $employeeID = $responseGetUser->employeeId;
	            } else {
		            return  redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);
	            }

            $responseVacRecords = $this->guzzleService->get(env('VACATION_BALANCE_API') . "vacationsRecords/{$employeeID}");

            if ($responseVacRecords->result == true) {
                $vacationHistory = $responseVacRecords->vacationsHistory;
            } else {
                $vacationHistory = '';
            }

            $response = $this->guzzleService->get(env('VACATION_BALANCE_API') . "getvacationbalance/{$employeeID}");
            if ($response->result == true) {
                $balanceData = $response->vacationsBalance[0];

                return view('process_forms.vacation.master', compact('vacation', 'tasks',
                    'formName', 'verifyUsers', 'vacations' , 'balanceData', 'vacationHistory'));
            } else {
                return redirect()->back()->with(['statusError' => 'Please contact System Admin, there\'s no balance.']);

            }


        }
        return \Redirect::Route('dashboard')->with([
            'status'     => "You don't have access to create Vacation Request",
            'statusType' => "danger"
        ]);


    }


    public function store(Request $request, $id, $taskId)
    {
        $start = startStopWatch();

        $taskId   = (int) $taskId;
        $tasksArr = Task::pluck('task_name', 'id');
        $usersArr = User::pluck('name', 'id');
        $logMSG   = [];
        $_request = [];

        $firstTask = Task::CheckIfIsFirstTaskOnTheWorkFlow($taskId, $this->vacationProcess);

        if ($firstTask === true) {
         VacationCase::findOrFail($id)->update([
                'title'                => 'Vacation -'.strtoupper(date('Y-M-d')).'-'.Auth::user()->name,
                'vacation_id'          => $request->vacation_id,
                'duration_of_vacation' => $request->vacation_duration,
                'starting_from'        => $request->starting_date,
                'till'                 => $request->till_date,
                'expected_return_date' => $request->return_date,

            ]);

        }


        $case = VacationCase::findOrFail($id);

        BeltoneCase::find($id)->update([
            'title'              => $case->title,
            'created_by_user_id' => $case->created_by_user_id,
            'current_user_id'    => $case->current_user_id,
            'task_id'            => $case->task_id,
            'status'             => $case->status
        ]);


        $getNextTaskWithUserID = Task::GetNextTaskWithUserID($taskId, $case, $request->all(), $this->vacationProcess);


        if (isset($getNextTaskWithUserID['nextTaskIDFromNormalWorkFlow'])) {
	        $_request['current_user_id'] = $getNextTaskWithUserID['nextUserID'];
	        $_request['status'] = 'TO_DO';
	        $_request['task_id'] = $getNextTaskWithUserID['nextTaskID'];
	        $_request['previous_user_id'] = $getNextTaskWithUserID['previousUserID'];
	        $_request['previous_task_id'] = $getNextTaskWithUserID['previousTaskID'] ? $getNextTaskWithUserID['previousTaskID'] : false;

	        if ($_request['task_id'] === false) {
		        unset($_request['task_id']);
		        $_request['status'] = 'completed';
	        }

	        if (isset($_request['task_id'])) {
		        $_request['task_name'] = $tasksArr[$_request['task_id']];
	        } else {
		        $_request['task_name'] = 'Completed';
	        }

	        if (!is_null($_request['current_user_id'])) {
		        $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
	        }

	        $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
	        $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];

	        $_request['current_user_name'] = $usersArr[$_request['current_user_id']];
	        $_request['previous_user_name'] = $usersArr[$_request['previous_user_id']];
	        $_request['previous_task_name'] = $tasksArr[$_request['previous_task_id']];
	        $_request['case_id'] = $id;
	        $_request['request'] = $request->all();
	        TempCaseData::where('case_id', $id)->whereNull('type')->delete();
	        TempCaseData::create([
		        'case_id' => $id, 'data' => json_encode($_request)
	        ]);

	        return redirect()->route('review_vacation_request_before_submit', $id);
        } else {

	        return redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);

        }

    }

    public function reviewRequest($id)
    {
        $start    = startStopWatch();
        $tempData = TempCaseData::where('case_id', $id)->whereNull('type')->first();
        $_request = (array) json_decode($tempData->data);
        $vacation = VacationCase::findOrFail($id);


        if (Auth::user()->id != $vacation->current_user_id) {

            return \Redirect::Route('dashboard')->with([
                'status'     => 'You cannot access this case',
                'statusType' => "danger"
            ]);
        }

        $employeeEmail = User::select('email')->where('id', $vacation->created_by_user_id)->get();
        $params = [
            "email" => $employeeEmail[0]->email
        ];

        $responseGetUser = $this->guzzleService->post(env('VACATION_BALANCE_API') . "getAdUser", [
            RequestOptions::JSON => $params
        ]);
        if (isset($responseGetUser->employeeId) && (! empty($responseGetUser->employeeId))) {
            $employeeID = $responseGetUser->employeeId;
        } else {
            return redirect()->route('dashboard')->with(['statusError' => 'Please contact System Admin.']);
        }

        Log::channel('info')->info("Case: $id - (confirmSubmit function): ".StopStopWatch($start).' ms');

        $params  = [
            "employeeId" => (int)$employeeID,
            "vacationType" => $vacation->vacation_id,
            "fromDate"    => $vacation->starting_from,
            "toDate"     => $vacation->till
        ];


        if ($vacation->task_id == env('VACATION_LINE_MANAGER_TASK_ID')) {

            $uri      = env('VACATION_BALANCE_API').'check-vacation';

            $response = $this->guzzleService->post($uri, [
                RequestOptions::JSON => $params
            ]);

           if ($response->result == true ) {
                $takenDays = $response->takenDays;
                return view('process_forms.vacation.review_vacation_request_before_submit',
                    compact('_request', 'vacation', 'takenDays'));
            } else {
                return redirect()->back()->with(['statusError' => $response->message ]);

           }

        } elseif ($vacation->task_id == env('VACATION_HR_TASK_ID')) {

            $uri      = env('VACATION_BALANCE_API').'submit-vacation/';
            $response = $this->guzzleService->post($uri, [
                RequestOptions::JSON => $params
            ]);

            if ($response->result == true) {
                $takenDays = $response->takenDays;
                return view('process_forms.vacation.review_vacation_request_before_submit',
                    compact('_request', 'vacation', 'takenDays'));
            } else {
                return redirect()->back()->with(['statusError' => $response->message]);
            }

        } else {
            return view('process_forms.vacation.review_vacation_request_before_submit',
                compact('_request', 'vacation'));
        }

    }

    public function confirmSubmit(Request $request)
    {

        try {

            $start = startStopWatch();

            $checkCurrentUser = VacationCase::find($request->id);
            if (Auth::user()->id != $checkCurrentUser->current_user_id) {
                return \Redirect::Route('dashboard')->with([
                    'status'     => 'You cannot access this case',
                    'statusType' => "danger"
                ]);
            }

            $tempData = TempCaseData::where('case_id', $request->id)->whereNull('type')->first();

            if (is_null($tempData)) {
                return \Redirect::Route('dashboard')->with([
                    'status'     => 'Cannot find Data for this case.
                                Please try to reopen the same case from inbox section and then submit before ask Support Team to handle it.',
                    'statusType' => "danger"
                ]);
            }

            $data = (array) json_decode($tempData->data);
            $id   = (int) $data['case_id'];

            $exceptKeys = [
                'goToUserIDToVerify', 'verifyTask', 'case_id', 'previous_task_name',
                'previous_user_name', 'current_user_name', 'task_name', 'request', 'caseAttachmentIds'
            ];

            $this->UpdateCaseAttachmentWithCommentID($id, $data, $this->vacationProcess);

            $participatedID = ParticipatedCase::create([
                'process_id'       => $this->vacationProcess,
                'case_id'          => $id,
                'task_id'          => isset($data['task_id']) ? $data['task_id'] : $data['previous_task_id'],
                'user_id'          => isset($data['current_user_id']) ? $data['current_user_id'] : null,
                'previous_user_id' => $data['previous_user_id'],
                'previous_task_id' => $data['previous_task_id'],
            ]);

            VacationCase::findOrFail($id)->update(array_except($data, $exceptKeys));

            $data['case_id'] = $id;
            VacationCaseLog::create(array_except($data, $exceptKeys));

            if (isset($data['current_user_name']) && !empty($data['current_user_name'])) {
                $statusMessage = "Your task has been sent to {$data['current_user_name']} ({$data['task_name']})";
            } elseif ($data['goToUserIDToVerify'] !== false && $data['verifyTask'] === false) {
                $statusMessage = "This case will go to selected user to verify it and then get back to you.";
            } elseif ($data['verifyTask'] === true && $data['goToUserIDToVerify'] === false) {
                $statusMessage = "This case will go to sender person.";
            } else {
                $statusMessage = "This case has been Completed.";
            }

            $vacationData = VacationCase::find($id);

            BeltoneCase::find($id)->update([
                'created_by_user_id' => $vacationData->created_by_user_id,
                'current_user_id'    => $vacationData->current_user_id,
                'task_id'            => $vacationData->task_id,
                'status'             => $vacationData->status,
                'previous_user_id'   => $data['previous_user_id'],
                'previous_task_id'   => $data['previous_task_id'],
                'read'               => 0
            ]);

            if ($vacationData->status == 'completed') {

                dispatch((new NotifyCompleteCaseJob($vacationData,
                    $this->vacationProcess))->delay(Carbon::now()->addSeconds(3)));

                BeltoneCase::find($id)->update([
                    'current_user_id' => $vacationData->created_by_user_id,
                    'status'          => 'completed',
                    'read'            => 0
                ]);
                VacationCase::findOrFail($id)->update(['current_user_id' => $vacationData->created_by_user_id]);
                VacationCaseLog::create(['current_user_id' => $vacationData->created_by_user_id]);

                ParticipatedCase::find($participatedID->id)->update([
                    'user_id' => $vacationData->created_by_user_id,
                ]);
            } else {
                dispatch((new NotifyUserJob($vacationData,
                    $this->vacationProcess))->delay(Carbon::now()->addSeconds(3)));
            }


            TempCaseData::where('case_id', $request->id)->delete();

            Log::channel('info')->info("Case: $id - (confirmSubmit function): ".StopStopWatch($start).' ms');
            return \Redirect::Route('dashboard')->with([
                'status'     => $statusMessage,
                'statusType' => "success"
            ]);
        } catch (RequestException $exception) {
            report($exception);
            return $exception->getMessage();
        }

    }

    private function UpdateCaseAttachmentWithCommentID($id, $data, $processID)
    {
        $participated = ParticipatedCase::caseID($id)->latest()->first();

        $comment = Comment::CreateCommentForCaseID($data['request'], $id, $data['previous_task_id'],
            $processID, $participated->id);
    }

}
