<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'groups';
    protected $guarded = [];

    public function scopeDepartment($query, $departmentName)
    {
        return $query->where('group_name', $departmentName);
    }

    public function scopeGroupName($query, $groupName)
    {
        return $query->where('group_name', $groupName);
    }

    public function scopeManagerID($query, $userID)
    {
        return $query->where('manager_user_id', $userID);
    }

    public function users()
    {
        return $this->hasMany('App\GroupUser', 'group_id', 'id');
    }
}
