<?php

namespace App\Http\Controllers;

use App\BeltoneCase;
use App\Comment;
use App\GroupUser;
use App\ParticipatedCase;
use App\PurchaseCase;
use App\PurchaseCaseLog;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function getInbox()
    {
        $users = User::active()->get();
        $cases = Beltonecase::current()->getTODOCases()->orderBy('id', 'desc')->get();
        return view('inbox', compact('cases', 'users'));
    }

    public function getDashboard()
    {
        $numOfInbox = BeltoneCase::current()->getTODOCases()->count();
        $numOfCompletedCases = BeltoneCase::current()->getCompletedCases()->count();
        $numOfMyCases = BeltoneCase::createdByUserID(Auth::user()->id)->count();
        $myCases = BeltoneCase::createdByUserID(Auth::user()->id)->getTODOCases()->get();
        $cases = Beltonecase::current()->getTODOCases()->orderBy('id', 'desc')->get();

        return view('dashboard', compact('numOfInbox', 'numOfMyCases',
                                            'numOfCompletedCases', 'myCases', 'cases'));
    }

    public function ajaxInbox()
    {
        $cases = Beltonecase::current()->getTODOCases()->orderBy('id', 'desc')->get();
        return view('ajax.inbox_view', compact('cases', 'users'));
    }

    public function getMyCases()
    {
        $myCases = BeltoneCase::createdByUserID(Auth::user()->id)->getCasesWithoutDrafts()->get();
        return view('display_my_cases', compact('myCases'));
    }

    public function getParticipated()
    {
        $participated = ParticipatedCase::distinct('case_id')->userID(Auth::user()->id)->pluck('case_id');
        $cases = BeltoneCase::whereIn('id', $participated)->getCasesWithoutDrafts()->get();
        return view('participated', compact('cases'));
    }

    public function getHistoryForCase($id)
    {
        $participated = ParticipatedCase::caseID($id)->get();

//        $comments = Comment::case($id)->get();
//
//        $commentsData = [];
//        for ($i = 0, $len = count($participated); $i < $len; $i++) {
//            $commentsData[$participated[$i]->id] = [];
//            for ($commentIndex = 0; $commentIndex < count($comments); $commentIndex++) {
//
//                if (   $participated[$i]->case_id == $comments[$commentIndex]->case_id
//                    && $participated[$i]->task_id == $comments[$commentIndex]->task_id
//                    && $participated[$i]->user_id == $comments[$commentIndex]->user_id
//                ) {
////                    $participated[$i]->commentData = $comments[$commentIndex]; // working but don't need it
//                    $commentsData[$participated[$i]->id]['comment'] = $comments[$commentIndex]->comment;
//                    $commentsData[$participated[$i]->id]['actionType'] = $comments[$commentIndex]->action_type;
//                }
//            }
//
//        }

        return view('ajax.case_history', compact('participated'));
    }

    public function displayAllCases()
    {
        $getUserGroups = GroupUser::getUserGroups(Auth::user()->id);
        if (in_array("46", $getUserGroups)) {
            $cases = Beltonecase::getCasesWithoutDrafts()->orderBy('id', 'desc')->get();
            return view('display_all_cases_for_users', compact('cases'));
        } else {
            $participated = ParticipatedCase::distinct('case_id')->userID(Auth::user()->id)->pluck('case_id')->toArray();
            $cases = Beltonecase::getCasesWithoutDrafts()
                ->Where(function ($q) use ($participated){
                    $q->whereIn('id', $participated)->created();
                })->orderBy('id', 'desc')->get();

            return view('display_all_cases_for_users', compact('cases'));
        }
    }

    public function displayCompanyCases()
    {
        $cases = BeltoneCase::getCasesRelatedToSpecificCompany()->orderBy('id', 'desc')->get();
        if (empty($cases[0]))
        {
            return \Redirect::Route('dashboard')->with([
                'status' => 'You cannot access company case',
                'statusType' => "danger"
            ]);
        }
        return view('display_all_cases_for_users', compact('cases'));

    }
}
