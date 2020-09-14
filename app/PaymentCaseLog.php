<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentCaseLog extends Model
{
    protected $table = 'payment_cases_log';
    protected $guarded = [];


    public function user()
    {
        return $this->belongsTo('App\User', 'created_by_user_id');
    }

    public function currentUser()
    {
        return $this->belongsTo('App\User', 'current_user_id');
    }

    public function task()
    {
        return $this->belongsTo('App\Task');
    }

    public function scopeCaseID($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public function scopeCreatedByUserID($query, $userID)
    {
        return $query->where('created_by_user_id', $userID);
    }
}
