<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $table = 'currencies_rate';
    protected $guarded = [];
    public $timestamps = false;

    public static function ConvertToEGP($amount, $fromCurrency)
    {
        $currencyData = CurrencyRate::where('curr_from', $fromCurrency)->where('curr_to', 'EGP')->first();

        if (! $currencyData)
            return \Redirect::Route('dashboard')->with([
                'status'    => 'Please rate for this currency ' . $fromCurrency . ' in database',
                'statusType'      => "danger"
            ]);

        return $amount * $currencyData->rate;
    }

    public static function ConvertFromTo($amount, $fromCurrency, $toCurrency)
    {
        $currencyData = CurrencyRate::where('curr_from', $fromCurrency)->where('curr_to', $toCurrency)->first();

        if (! $currencyData)
            return \Redirect::Route('dashboard')->with([
                'status'    => 'Please rate for this currency ' . $fromCurrency . ' in database',
                'statusType'      => "danger"
            ]);

        return $amount * $currencyData->rate;
    }
}
