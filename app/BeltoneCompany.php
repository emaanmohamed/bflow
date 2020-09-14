<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BeltoneCompany extends Model
{
    protected $table = 'beltone_companies';
    protected $guarded = [];

    public static function GetSubCompaniesForCompanyID($code)
    {
        return BeltoneCompany::where('parent_comp_code', $code)->pluck('comp_name', 'comp_code');
    }

    public static function GetBLOForCompanyID($code, $getBLOID = false)
    {
        $data = BeltoneCompany::where('comp_code', $code)->get();

        if ($getBLOID === true) {
            $username = isset($data[0]->business_owner_username) ? $data[0]->business_owner_username : false;
            $bloData = User::where('username', $username)->first();
            return $bloData->id;
        }

        return isset($data[0]->business_owner_username) ? $data[0]->business_owner_username : false;
    }
}
