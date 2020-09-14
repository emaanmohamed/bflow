<?php

namespace App\Http\Controllers;

use App\BeltoneCase;
use App\CompanyUsers;
use App\GroupUser;
use App\ParticipatedCase;
use App\PaymentCase;
use App\Process;
use App\PurchaseCase;
use App\SettlementCase;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AdvancedSearchController extends Controller
{
    public  $exceptValues = ['page'];
    private $searchInCreatedAt = [];
    private $arrayColumnsUsingLike = [];

    public function index()
    {
        $users     = User::active()->get();
        $processes = Process::get();
        return view('advanced_search.display', compact('users', 'processes'));

    }

    public function getSearch(Request $request)
    {
        $getUserGroups = GroupUser::getUserGroups(Auth::user()->id);
        $getUsersInCompanyUsers = CompanyUsers::pluck('user_id')->toArray();
        $users      = User::active()->get();
        $processes  = Process::get();

        if (in_array("46", $getUserGroups)) {
            $query        =  BeltoneCase::with('purchaseAmount', 'settlementAmount', 'paymentAmount')->whereIn('process_id', [1,2,3]);
            $query = $this->advancedSearch($query, $request);
            $numOfCases = $query->numOfCases;
            $cases = $query->cases;

            return view('advanced_search.display', compact('cases', 'users', 'numOfCases', 'processes'));

        } elseif (in_array(Auth::user()->id, $getUsersInCompanyUsers)) {

            $query = BeltoneCase::getCasesRelatedToSpecificCompany();

            $query = $this->advancedSearch($query, $request);

            $numOfCases = $query->numOfCases;
            $cases = $query->cases;

            return view('advanced_search.display', compact('cases', 'users', 'numOfCases', 'processes'));

        } else {
            $participated = ParticipatedCase::distinct('case_id')->userID(Auth::user()->id)->pluck('case_id')->toArray();
            $query = Beltonecase::getCasesWithoutDrafts()
                ->Where(function ($q) use ($participated){
                    $q->whereIn('id', $participated)->created();
                });
            $query = $this->advancedSearch($query, $request);
            $numOfCases = $query->numOfCases;
            $cases = $query->cases;

            return view('advanced_search.display', compact('cases', 'users', 'numOfCases', 'processes'));

        }

    }

    public function advancedSearch($query, $request)
    {

        $query  = $this->searchInCreatedAt($query, $request);

        foreach (array_except($request->all(), $this->exceptValues) as $key => $value) {
            $query = $this->searchOnColumnsUsingLike($query, $key, $value);
            $query = $this->searchOnColumnsUsingEqual($query, $key, $value);
        }

        $query->numOfCases = $query->getCompletedAndTODOCases()->count();
        $query->cases      = $query->getCompletedAndTODOCases()->orderBy('id', 'desc')->paginate(10);

        return $query ;

    }

    private function searchInCreatedAt($query, $request)
    {
        $this->searchInCreatedAt = ['dateFrom', 'dateTo'];
        if (! is_null($request->dateFrom) && ! empty($request->dateFrom) && !is_null($request->dateTo) && !empty($request->dateTo)) {
            return $query->where('created_at', '>=', $request->dateFrom)->where('created_at', '<=', $request->dateTo);
        }
        return $query;
    }

    private function searchOnColumnsUsingLike($query, $key, $value)
    {
        $this->arrayColumnsUsingLike = ['title'];
        if (! in_array($key, $this->arrayColumnsUsingLike) || is_null($value)) {
            return $query;
        }
        return $query->where($key, 'like', "%{$value}%");

    }

    private function searchOnColumnsUsingEqual($query, $key, $value)
    {
        if (is_null($value)
            || in_array($key, $this->arrayColumnsUsingLike)
            || in_array($key, $this->searchInCreatedAt)) {
            return $query;
        }
        return $query->where($key, $value);

    }

}
