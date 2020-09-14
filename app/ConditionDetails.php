<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConditionDetails extends Model
{
    protected $table = 'conditions_details';
    protected $guarded = [];

    public function scopeTask($query, $taskConditionID)
    {
        return $query->where('task_condition_id', $taskConditionID);
    }

    public static function Check($conditionID, $case, $logMSG = null)
    {
        $conditionLog = caseLog($case->id);
        $details = ConditionDetails::task($conditionID)->get();

        if (isset($case->invoice_currency) && $case->invoice_currency != 'EGP')
            $case->amount = CurrencyRate::ConvertToEGP($case->amount, $case->invoice_currency);

        for ($i = 0, $len = count($details); $i < $len; $i++) {

            $logMSG['conditionID']['ConditionDetails'][$details[$i]->id] = [
                        'column_name' => $details[$i]->column_name
                    ];

            $columnValue = isset($case->toArray()[$details[$i]->column_name])
                ? $case->toArray()[$details[$i]->column_name] : null;

            if (ifConditionUsingStringOperator(
                $conditionLog,
                $columnValue,
                $details[$i]->operator,
                $details[$i]->value, $details[$i]->value_2)
            ) {
                $logMSG['conditionID']['ConditionDetails'][$details[$i]->id] = [
                    'routing_task_id'   => $details[$i]->routing_task_id,
                    'routing_user_id'   => $details[$i]->routing_user_id,
                ];

                if (count($logMSG))
                    $conditionLog->addinfo(print_r($logMSG, true));

                return [
                    'routing_task_id' => $details[$i]->routing_task_id,
                    'routing_user_id' => $details[$i]->routing_user_id,
                    'condition_id'    => $conditionID,
                    'condition_details_id'  => $details[$i]->id
                ];
            } else {
                if (is_null($details[$i]->column_name)) {
                    $logMSG['conditionID']['ConditionDetails'][$details[$i]->id] = [
                        'routing_task_id'   => $details[$i]->routing_task_id,
                        'routing_user_id'   => $details[$i]->routing_user_id,
                    ];

                    if (count($logMSG))
                        $conditionLog->addinfo(print_r($logMSG, true));

                    return [
                        'routing_task_id' => $details[$i]->routing_task_id,
                        'routing_user_id' => $details[$i]->routing_user_id,
                        'condition_id'    => $conditionID,
                        'condition_details_id'  => $details[$i]->id
                    ];
                }
            }

        }

//        $logMSG['conditionID'] = "The result from condition details === False";
//        $log->addInfo(print_r($logMSG, true));
        return false;
    }

    public static function CheckAssignedUser($conditionID, $case)
    {
        $details = ConditionDetails::task($conditionID)->get();

        if (count($details) === 1)
            return $details[0]->routing_user_id;

        for ($i = 0, $len = count($details); $i < $len; $i++) {

            if (ifConditionUsingStringOperator(
                $case->toArray()[$details[$i]->column_name],
                $details[$i]->operator,
                $details[$i]->value, $details[$i]->value_2)
            ) {
                return is_null($details[$i]->routing_user_id) ? false : $details[$i]->routing_user_id;
            }

        }

        return false;
    }
}
