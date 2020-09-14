<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PurchaseCase extends Model
{
    protected $table = 'purchase_cases';
    protected $guarded = [];

    public static function getCountOfTasks()
    {
        return Task::where('process_id', env('PURCHASE_PROCESS'))->count();
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'created_by_user_id');
    }

    public function company()
    {
        return $this->belongsTo('App\BeltoneCompany', 'company_id', 'comp_code');
    }

    public function vendor()
    {
        return $this->belongsTo('App\BeltoneVendor', 'vendor_code', 'vendor_code');
    }

    public function currentUser()
    {
        return $this->belongsTo('App\User', 'current_user_id');
    }

    public function previousUser()
    {
        return $this->belongsTo('App\User', 'previous_user_id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task');
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

    public function scopeCreated($query)
    {
        return $query->orWhere('created_by_user_id', Auth::user()->id);
    }

    public function scopeCreatedByUserID($query, $userID)
    {
        return $query->where('created_by_user_id', $userID);
    }
}
