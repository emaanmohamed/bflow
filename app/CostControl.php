<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CostControl extends Model
{
    protected $table = 'cost_control';
    protected $guarded = [];
    public $timestamps = false;

    public function scopeCaseID($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

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

    public static function CreateCostControlForPurchaseProcess($request, $caseId)
    {
        $taskID = PurchaseCase::find($caseId)->task_id;

        if ($taskID == 4) {
            CostControl::where('case_id', $caseId)->delete();

            if (isset($request['purchase_parent_company_code'])) {
                for ($i = 0, $len = count($request['purchase_parent_company_code']); $i < $len; $i++) {
                    $parentCompanyCode = $request['purchase_parent_company_code'][$i];
                    $subCompanyCode = isset($request['purchase_sub_company_code'][$i])
                                        ? $request['purchase_sub_company_code'][$i] : null;
                    $costCenterCode = $request['purchase_cost_center_code'][$i];

                    CostControl::create([
                        'case_id' => $caseId,
                        'parent_company_code' => $parentCompanyCode == 'select_company' ? null : $parentCompanyCode,
                        'sub_company_code' => $subCompanyCode == 'select_sub_company' ? null : $subCompanyCode,
                        'cost_center_code' => $costCenterCode == 'select_cost_center' ? null : $costCenterCode,
                        'distribution' => $request['purchase_distribution'][$i],
                        'budget' => $request['purchase_budget'][$i],
                        'as_contract' => $request['purchase_as_contract'][$i],
                        'comment' => $request['purchase_comment'][$i],
                    ]);
                }
            }
        }
    }

    public static function CreateCostControlForPaymentProcess($request, $caseId)
    {
        $taskOrder = PaymentCase::find($caseId)->task->task_order;

        if ($taskOrder == 1) {
            CostControl::caseID($caseId)->delete();

            if (isset($request['payment_parent_company_code'])) {
                for ($i = 0, $len = count($request['payment_parent_company_code']); $i < $len; $i++) {
                    $parentCompanyCode = $request['payment_parent_company_code'][$i];
                    $subCompanyCode = isset($request['payment_sub_company_code'][$i])
                                        ? $request['payment_sub_company_code'][$i] : null;
                    $costCenterCode = $request['payment_cost_center_code'][$i];

                    CostControl::create([
                        'case_id' => $caseId,
                        'parent_company_code' => $parentCompanyCode == 'select_company' ? null : $parentCompanyCode,
                        'sub_company_code' => $subCompanyCode == 'select_sub_company' ? null : $subCompanyCode,
                        'cost_center_code' => $costCenterCode == 'select_cost_center' ? null : $costCenterCode,
                        'distribution' => $request['payment_distribution'][$i],
                        'budget' => $request['payment_budget'][$i],
                        'as_contract' => $request['payment_as_contract'][$i],
                        'comment' => $request['payment_comment'][$i],
                    ]);
                }
            }
        }
    }

    public static function CreateCostControlForSettlementProcess($request, $caseId)
    {
        $taskOrder = SettlementCase::find($caseId)->task->task_order;

        if ($taskOrder == 2) {
            CostControl::caseID($caseId)->delete();

            if (isset($request['purchase_parent_company_code'])) {

                for ($i = 0, $len = count($request['purchase_parent_company_code']); $i < $len; $i++) {
                    $parentCompanyCode = $request['purchase_parent_company_code'][$i];
                    $subCompanyCode = isset($request['purchase_sub_company_code'][$i])
                        ? $request['purchase_sub_company_code'][$i] : null;
                    $costCenterCode = $request['purchase_cost_center_code'][$i];

                    CostControl::create([
                        'case_id' => $caseId,
                        'parent_company_code' => $parentCompanyCode == 'select_company' ? null : $parentCompanyCode,
                        'sub_company_code' => $subCompanyCode == 'select_sub_company' ? null : $subCompanyCode,
                        'cost_center_code' => $costCenterCode == 'select_cost_center' ? null : $costCenterCode,
                        'distribution' => $request['purchase_distribution'][$i],
                        'budget' => $request['purchase_budget'][$i],
                        'as_contract' => $request['purchase_as_contract'][$i],
                        'comment' => $request['purchase_comment'][$i],
                    ]);

                }
            }
        }
    }
}
