<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    public static function CheckProcessAccess($processId, $userId)
    {
        $getGroupsHasAccess = ProcessAccess::where('process_id', $processId)->pluck('group_id')->toArray();
        $getUserGroups = GroupUser::getUserGroups($userId);

        if (! count($getUserGroups))
            return false;
        else {
            for ($i = 0, $len = count($getUserGroups); $i < $len; $i++) {
                if (in_array($getUserGroups[$i], $getGroupsHasAccess))
                    return true;
            }

            return false;
        }
    }

    public static function GetLineManager($userId)
    {
        return GroupUser::getLineManagerForUserID($userId);
    }

    public static function GetBLOManager(PurchaseCase $case)
    {
        return GroupUser::getBLOForCompanyID($case);
    }

    public static function GetPurchaseDepartmentManager()
    {
        return GroupUser::getPurchaseDepartmentManager();
    }

    public function scopeUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

}
