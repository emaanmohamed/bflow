<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseBLOCostCenter extends Model
{
    protected $table = 'purchase_blo_cost_control';
    protected $guarded = [];
    public $timestamps = false;


    public function parentCompany()
    {
        return $this->belongsTo("App\BeltoneCompany", 'parent_company_code', 'comp_code');
    }

    public function subCompany()
    {
        return $this->belongsTo("App\BeltoneCompany", 'sub_company_code', 'comp_code');
    }

    public function costCenter()
    {
        return $this->belongsTo("App\BeltoneCostCenter", 'cost_center_code', 'ID');
    }

    public static function CreateBLOCostControlForCaseID($request, $caseId)
    {
        $taskID = PurchaseCase::find($caseId)->task_id;

        if ($taskID == 3) {
            PurchaseBLOCostCenter::where('case_id', $caseId)->delete();

            if (isset($request['blo_parent_company_code'])) {
                for ($i = 0, $len = count($request['blo_parent_company_code']); $i < $len; $i++) {
                    $parentCompanyCode = $request['blo_parent_company_code'][$i];
                    $subCompanyCode = isset($request['blo_sub_company_code'][$i])
                                        ? $request['blo_sub_company_code'][$i] : null;
                    $costCenterCode = $request['blo_cost_center_code'][$i];

                    PurchaseBLOCostCenter::create([
                        'case_id' => $caseId,
                        'parent_company_code' => $parentCompanyCode == 'select_company' ? null : $parentCompanyCode,
                        'sub_company_code' => $subCompanyCode == 'select_sub_company' ? null : $subCompanyCode,
                        'cost_center_code' => $costCenterCode == 'select_cost_center' ? null : $costCenterCode,
                        'distribution' => $request['blo_distribution'][$i],
                        'budget' => $request['blo_budget'][$i],
                        'as_contract' => $request['blo_as_contract'][$i],
                        'comment' => $request['blo_comment'][$i],
                    ]);
                }
            }
        }
    }
}
