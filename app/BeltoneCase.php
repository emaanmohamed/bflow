<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BeltoneCase extends Model
{
    protected $table = 'cases';
    protected $guarded = [];
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('App\User', 'created_by_user_id');
    }

    public function currentUser()
    {
        return $this->belongsTo('App\User', 'current_user_id');
    }

//    public function caseCreator()
//    {
//        return $this->belongsTo('App\User', 'created_by_user_id');
//    }

    public function previousUser()
    {
        return $this->belongsTo('App\User', 'previous_user_id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task');
    }

    public function process()
    {
        return $this->belongsTo('App\Process');
    }

    public function previousTask()
    {
        return $this->belongsTo('App\Task', 'previous_task_id');
    }

    public function scopeCurrent($query)
    {
        return $query->orWhere('current_user_id', Auth::user()->id);
    }

    public function scopeGetTODOCases($query)
    {
        return $query->where('status', 'TO_DO');
    }

    public function scopeGetCompletedCases($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeGetCasesWithoutDrafts($query)
    {
        return $query->where('status', '<>', 'Draft');
    }

    public function scopeCreated($query)
    {
        return $query->orWhere('created_by_user_id', Auth::user()->id);
    }

    public function scopeGetCompletedAndTODOCases($query)
    {
        return $query->where('status', 'TO_DO')->orWhere('status', 'completed');
    }

    public function scopeCreatedByUserID($query, $userID)
    {
        return $query->where('created_by_user_id', $userID);
    }

    public function scopeGetCasesWithoutDraftsRelatedToSpecificUser($query)
    {
        return $query->where('status', '<>', 'Draft')->where('created_by_user_id', Auth::user()->id)->orWhere('current_user_id', Auth::user()->id)->orWhere('previous_user_id', Auth::user()->id);

    }

    public function scopeGetCasesRelatedToSpecificCompany()
    {
        $result = $this->select(DB::RAW("`cases`.*,IFNULL((SELECT company_id FROM purchase_cases where purchase_cases.id = cases.id) , IFNULL((SELECT company_id FROM payment_cases where payment_cases.id = cases.id),
         (SELECT company_id FROM settlement_cases where settlement_cases.id = cases.id)))
         as company_id"))->whereRaw("IFNULL((SELECT company_id FROM purchase_cases where purchase_cases.id = cases.id) , IFNULL((SELECT company_id FROM payment_cases where payment_cases.id = cases.id),
         (SELECT company_id FROM settlement_cases where settlement_cases.id = cases.id))) in (SELECT company_id from company_users WHERE user_id = ?)" , Auth::user()->id);

        return $result;
    }

    public function purchaseAmount()
    {
        return $this->belongsTo('App\PurchaseCase', 'id');
    }

    public function settlementAmount()
    {
        return $this->belongsTo('App\SettlementCase', 'id');
    }

    public function paymentAmount()
    {
        return $this->belongsTo('App\PaymentCase', 'id');
    }




}
