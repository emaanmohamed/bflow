<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GroupUser extends Model
{
    protected $table = 'group_users';
    protected $guarded = [];

    public function _user()
    {
        return $this->belongsTo("App\User", "user_id");
    }

    public function scopeGroupID($query, $groupID)
    {
        return $query->where('group_id', $groupID);
    }

    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function getUserGroups($userId)
    {
        return GroupUser::where('user_id', $userId)->pluck('group_id')->toArray();
    }

    public static function getLineManagerForUserID($userId)
    {
        return DB::table('groups as g')->select(['g.manager_user_id'])
                    ->join('group_users as gu', 'gu.group_id', '=', 'g.id')
                    ->where('g.type', 'department')
                    ->where('gu.user_id', $userId)->pluck('manager_user_id')->toArray();
    }

    public static function getBLOForCompanyID($case, $logMSG = null)
    {
      if (! is_null($case->company_id)) {
          if (isset($case->company_id)) {
              $bloUser = BeltoneCompany::where('comp_code', $case->company_id)->first();
          }
          if (is_numeric($case)) {
              $case    = PurchaseCase::find($case);
              $bloUser = BeltoneCompany::where('comp_code', $case->company_id)->first();
          }

          $userID = User::username($bloUser->business_owner_username)->first();

          return $userID->id;
      }
      return 48;

    }

    public static function getPurchaseDepartmentManager()
    {
        return Group::department('purchase')->pluck('manager_user_id')->toArray();
    }

    public static function getDepartmentForUserID($userID, $group)
    {
        if (is_null($group->type))
            return null;

        return DB::table('group_users as gu')->select(["gu.group_id", "g.manager_user_id"])
            ->join('groups as g', 'gu.group_id', '=', 'g.id')
            ->where('gu.user_id', (int) $userID)
            ->where('g.type', $group->type)
            ->first();
    }

    public static function getManagerForDepartment($case, $group, $logMSG = null)
    {
        $department = GroupUser::getDepartmentForUserID($case->current_user_id, $group);

        if (isset($department->manager_user_id) && ! is_null($department->manager_user_id)) {
            return $department->manager_user_id;
        } else {
            return null;
        }

    }

    public static function getManagerForGroupID($groupID, $case, $logMSG = null)
    {
        $group = Group::find($groupID->group_id);

        if (! is_null($group) && $group->group_name == "BLO") {
            return GroupUser::getBLOForCompanyID($case);
        }

        if (is_null($group)) {
            return GroupUser::getManagerForDepartment($case, $groupID, $logMSG['getManagerForDepartment'] = null);
        }

        if (is_null($group->manager_user_id)) {
            $logMSG['getManagerForDepartment']['manager_user_id'] = null;

            return GroupUser::getManagerForDepartment($case, $groupID, $logMSG['getManagerForDepartment']);
        } else {
            return $group->manager_user_id;
        }
    }
}
