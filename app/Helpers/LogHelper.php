<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function caseLog($caseId, $path = '/logs/log-cases/')
{
    // the default date format is "Y-m-d H:i:s"
    $dateFormat = "Y-m-d H:i:s.u";

    // finally, create a formatter
    $formatter = new LineFormatter();

    $log = new Logger("case-$caseId");
    $log->pushHandler((new StreamHandler(storage_path($path . 'case-' . $caseId . '.log'), Logger::INFO))->setFormatter($formatter));

    return $log;
}

function LoginLog($path = '/logs/login-log/')
{
    // the default date format is "Y-m-d H:i:s"
    $dateFormat = "Y-m-d H:i:s.u";

    // finally, create a formatter
    $formatter = new LineFormatter();

    $log = new Logger("login");
    $log->pushHandler((new StreamHandler(storage_path($path . 'login' . '.log'), Logger::INFO))->setFormatter($formatter));

    return $log;
}