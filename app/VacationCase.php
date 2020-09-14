<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VacationCase extends Model
{
    protected $table = 'vacation_cases';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\User', 'created_by_user_id');
    }

    public function scopeCaseID($query, $caseID)
    {
        return $query->where('id', $caseID);
    }
}
