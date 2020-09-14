<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SettlementItem extends Model
{
    protected $table = 'settlement_items_breakdown';
    protected $guarded = [];

    public function scopeCaseID($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public static function CreateItemDetailsForCaseID(Request $request, $caseId)
    {
        SettlementItem::where('case_id', $caseId)->delete();

        $total = 0;
        if (isset($request->item_details_amount) && ! empty($request->item_details_amount) &&
            isset($request->item_details_currency) && ! empty($request->item_details_currency) &&
            isset($request->item_details_description) && isset($request->item_details_vendor))
        {
            for ($i = 0, $len = count($request->item_details_amount); $i < $len; $i++) {
                $amount = strpos($request->item_details_amount[$i], ',')
                    ? str_replace(',', '', $request->item_details_amount[$i])
                    : $request->item_details_amount[$i];


                if ($request->item_details_currency[$i] != 'EGP')
                    $amountInEGP = CurrencyRate::ConvertToEGP($amount, $request->item_details_currency[$i]);
                else
                    $amountInEGP = $amount;

                $total += $amountInEGP;

                SettlementItem::create([
                    'case_id'             => $caseId,
                    'case'               => $request->item_details_case[$i],
                    'description'            => $request->item_details_description[$i],
                    'vendor'               => $request->item_details_vendor[$i],
                    'transaction_date'         => $request->item_details_transaction_date[$i],
                    'currency'         => $request->item_details_currency[$i],
                    'amount'         => $amount,
                    'egp_amount'         => $amountInEGP,
                ]);
            }
        }

        return $total;

    }
}
