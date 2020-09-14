<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

function _check($var)
{
    return (isset($var) && ! is_null($var)) ? true : false;
}

function getCurrentDate($format = 'Y-m-d')
{
    return date_format(date_create(), $format);
}

function getDateTimeNow()
{
    date_default_timezone_set('Africa/Cairo');
    return dateForSearch('');
}

function addMinutesToDateTime($datetime, $minutes)
{
    $time = new DateTime($datetime);
    $time->add(new DateInterval('PT' . $minutes . 'M'));

    return $time->format('Y-m-d H:i:s');
}

function dateForSearch($date, $format = 'Y-m-d H:i:s')
{
    return date_format(date_create($date), $format);
}

function getDayFromDate($date)
{
    $date = DateTime::createFromFormat("Y-m-d", $date);
    return $date->format("d M");
}

function convertStringDateToTime($stringDate, $format = 'Y-m-d H:i:s')
{
    $time = strtotime($stringDate);
    $formatDate = date($format, $time);
    return $formatDate;
}

function _oldData($fieldName)
{
    return session($fieldName) ? session($fieldName) : '';
}

function ifConditionUsingStringOperator($conditionLog, $columnName, $operator,
                                        $firstValue, $secondValue = null)
{
    $logMSG['functionName'] = 'ifConditionUsingStringOperator';
    if ($operator == '==') {
        if ($columnName == $firstValue) {
            $logMSG['=='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG, true));
            return true;
        } else {
            $logMSG['=='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];
        }
    } elseif ($operator == '>') {
        if ($columnName > $firstValue) {
            $logMSG['>'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG, true));
            return true;
        } else {
            $logMSG['>'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];

        }
    } elseif ($operator == '>=') {
        if ($columnName >= $firstValue) {
            $logMSG['>='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG, true));
            return true;
        } else {
            $logMSG['>='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];
        }
    } elseif ($operator == '<') {
        if ($columnName < $firstValue) {
            $logMSG['<'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG, true));
            return true;
        } else {
            $logMSG['<'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];
        }
    } elseif ($operator == '!=') {
        if ($columnName != $firstValue) {
            $logMSG['!='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG, true));
            return true;
        } else {
            $logMSG['!='] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];
        }
    } elseif ($operator == '<>') {
        if ($columnName <> $firstValue) {
            $logMSG['<>'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG));
            return true;
        } else {
            $logMSG['<>'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'result'    => false
            ];
        }
    } elseif ($operator == 'between') {
        if (($columnName >= $firstValue) && ($columnName <= $secondValue)) {
            $logMSG['between'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'secondValue'    => $secondValue,
                'result'    => true
            ];

            $conditionLog->addinfo(json_encode($logMSG));
            return true;
        } else {
            $logMSG['between'] = [
                'columnName' => $columnName,
                'firstValue'    => $firstValue,
                'secondValue'    => $secondValue,
                'result'    => false
            ];

        }
    }

    $conditionLog->addinfo("No conditions has been applied");
    return false;
}

function checkActionType($request)
{
    if (isset($request['line_manager_approval']))
        return ($request['line_manager_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['contact_person_approval']))
        return ($request['contact_person_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['second_line_manager_approval']))
        return ($request['second_line_manager_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['contact_person_review']))
        return ($request['contact_person_review'] == 2) ? 'approve' : 'reject';

    if (isset($request['cfo_approval_first']))
        return ($request['cfo_approval_first'] == 1) ? 'approve' : 'reject';

    if (isset($request['blo_approval']))
        return ($request['blo_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['purchase_approval']))
        return ($request['purchase_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['coo_approval']))
        return ($request['coo_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['cfo_approval']))
        return ($request['cfo_approval'] == 1) ? 'approve' : 'reject';

    if (isset($request['payment_complete']))
        return ($request['payment_complete'] == 1) ? 'approve' : 'reject';

    return \Redirect::Route('dashboard')->with([
        'status'    => 'checkActionType not found',
        'statusType'      => "danger"
    ]);

//    return 'approve';
}

function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function PrintApprovalPreviewCase($approval)
{
    if ($approval === 1)
        return "<strong style=\"background-color: green;color: white;padding: 3px;border-radius: 3px\">Approval</strong>";
    elseif ($approval === 0)
        return "<strong style=\"background-color: red;color: white;padding: 3px;border-radius: 3px\">Rejected</strong>";
    else
        return "<strong style=\"background-color: black;color: white;padding: 3px;border-radius: 3px\">None</strong>";
}

function PrintYesOrNOPreviewCase($approval)
{
    if ($approval === 1)
        return "<strong style=\"background-color: green;color: white;padding: 3px;border-radius: 3px\">Yes</strong>";
    elseif ($approval === 0)
        return "<strong style=\"background-color: red;color: white;padding: 3px;border-radius: 3px\">No</strong>";
    else
        return "<strong style=\"background-color: black;color: white;padding: 3px;border-radius: 3px\">None</strong>";
}

function startStopWatch()
{
    return microtime(true);
}

function StopStopWatch($startValue)
{
    return (microtime(true) - $startValue) * 1000;
}

function GetNameOfUser()
{
    return Auth::user()->name;
//    return Auth::user()->first_name . ', ' . Auth::user()->last_name;
}

function GetBaseTitleForCase($request, $caseID)
{
    $shortCompanyCode = App\BeltoneCompany::where('comp_code', $request->company_id)->first()->short_code;
    return $shortCompanyCode . '-' . strtoupper(date('Y-M-d')) . '-' . $caseID;
}

function getStringBetween($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function actionTypes($id)
{
    $arr = [
        'Rejected', 'Approved', 'Verify', 'Reassigned', 'COMPLETED'
    ];

    return isset($arr[$id]) ? $arr[$id] : '';
}

function actionTypeStyle($id)
{
    if ($id === 0)
        return 'background-color: red;color: white;font-size: 16px;padding: 5px;border-radius: 3px;';
    elseif ($id === 1)
        return 'background-color: darkgreen;color: white;font-size: 16px;padding: 5px;border-radius: 3px;';
    elseif ($id === 2)
        return 'background-color: black;color: white;font-size: 16px;padding: 5px;border-radius: 3px;';
    elseif ($id === 4)
        return 'background-color: darkgreen;color: white;font-size: 16px;padding: 5px;border-radius: 3px;';

    return '';
}

function GetCurrencies()
{
    return [
        'EGP'   => 'EGP',
        'CAD'   => 'CAD',
        'USD'   => 'USD',
        'GBP'   => 'GBP',
        'EUR'   => 'EUR',
        'AED'   => 'AED',
        'SAR'   => 'SAR',
        'ZAR'   => 'ZAR',
        'AUD'   => 'AUD',
        'MAD'   => 'MAD',
        'KWD'   => 'KWD',
	'RUB'   => 'RUB',
	'KES' 	=> 'KES'
    ];
}

function GetCurrency($currency)
{
    return GetCurrencies()[$currency];
}

function convertCurrency($from, $to, $amount){
    $url = file_get_contents('https://free.currencyconverterapi.com/api/v5/convert?q=' . $from . '_' . $to . '&compact=ultra');
    $json = json_decode($url, true);
    $rate = implode(" ",$json);
    $total = $rate * $amount;
    return $total; //or return $rounded if you kept the rounding bit from above
}


function getOldData($data)
{
    return $_GET[$data] ?? '';
}
