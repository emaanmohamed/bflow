<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BeltoneCostCenter extends Model
{
    protected $table = 'bel_costcenters';
    protected $guarded = [];

    public static function GetCodeCenterForCompanyCode($companyCode)
    {
        return BeltoneCostCenter::where('COSTCENTER_CODE', 'like', '%' . $companyCode)
                                ->orderBy('COSTCENTER_CODE')->get();
    }
}
