<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $table = 'bank_transactions';
    protected $guarded = [];
    public $timestamps = false;


    public function company()
    {
        return $this->belongsTo("App\BeltoneCompany", 'company_code', 'comp_code');
    }

    public function bank()
    {
        return $this->belongsTo("App\BeltoneBankList", 'bank_id', 'BANK_ID');
    }

    public static function CreateBankTransactionForPurchaseRequest($request, $caseId)
    {
        $taskID = PurchaseCase::find($caseId)->task_id;

        if ($taskID == 6) {
            BankTransaction::where('case_id', $caseId)->delete();

            if (isset($request['finance_company_code'])) {
                for ($i = 0, $len = count($request['finance_company_code']); $i < $len; $i++) {
                    $companyCode = $request['finance_company_code'][$i];
                    $bankCode = $request['finance_bank_code'][$i];
                    $amount = $request['finance_amount'][$i];
                    $currency = $request['finance_currency'][$i];

                    BankTransaction::create([
                        'case_id' => $caseId,
                        'bank_id' => $bankCode,
                        'company_code' => $companyCode,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]);
                }
            }
        }
    }


    public static function CreateBankTransactionForPaymentRequest($request, $caseId)
    {
        $taskID = PaymentCase::find($caseId)->task_id;

        if ($taskID == 18) {
            BankTransaction::where('case_id', $caseId)->delete();

            if (isset($request['finance_company_code'])) {
                for ($i = 0, $len = count($request['finance_company_code']); $i < $len; $i++) {
                    $companyCode = $request['finance_company_code'][$i];
                    $bankCode = $request['finance_bank_code'][$i];
                    $amount = $request['finance_amount'][$i];
                    $currency = $request['finance_currency'][$i];

                    BankTransaction::create([
                        'case_id' => $caseId,
                        'bank_id' => $bankCode,
                        'company_code' => $companyCode,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]);
                }
            }
        }
    }

    public static function CreateBankTransactionForSettlementRequest($request, $caseId)
    {
        $taskID = SettlementCase::find($caseId)->task_id;

        if ($taskID == 26) {
            BankTransaction::where('case_id', $caseId)->delete();

            if (isset($request['finance_company_code'])) {
                for ($i = 0, $len = count($request['finance_company_code']); $i < $len; $i++) {
                    $companyCode = $request['finance_company_code'][$i];
                    $bankCode = $request['finance_bank_code'][$i];
                    $amount = $request['finance_amount'][$i];
                    $currency = $request['finance_currency'][$i];

                    BankTransaction::create([
                        'case_id' => $caseId,
                        'bank_id' => $bankCode,
                        'company_code' => $companyCode,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]);
                }
            }
        }
    }
}
