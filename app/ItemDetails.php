<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ItemDetails extends Model
{
    protected $table = 'item_details';
    protected $guarded = [];


    public function scopeCaseID($query, $caseID)
    {
        return $query->where('case_id', $caseID);
    }

    public static function CreateItemDetailsForCaseID(Request $request, $caseId)
    {
        ItemDetails::where('case_id', $caseId)->delete();

        $total = 0;
        if (isset($request->price) && ! empty($request->price) &&
            isset($request->quantity) && ! empty($request->quantity) &&
            isset($request->description))
        {
            for ($i = 0, $len = count($request->price); $i < $len; $i++) {
                $price = strpos($request->price[$i], ',')
                            ? str_replace(',', '', $request->price[$i])
                            : $request->price[$i];
                $itemTotal = round(($price * $request->quantity[$i]), 2);
                $total += $itemTotal;

                ItemDetails::create([
                    'case_id'             => $caseId,
                    'price'               => $price,
                    'quantity'            => $request->quantity[$i],
                    'total'               => $itemTotal,
                    'description'         => $request->description[$i]
                ]);
            }
        }

        return $total;

    }
}
