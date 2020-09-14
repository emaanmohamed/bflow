<?php

namespace App\Jobs;

use App\Triats\NotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotifyCompleteCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $_case;
    protected $_processID;

    public function __construct($case, $processID)
    {
        $this->_case = $case;
        $this->_processID = $processID;
    }

    public function handle()
    {
        $this->NotifyCompleteCaseViaEmail($this->_case, $this->_processID);
    }

}
