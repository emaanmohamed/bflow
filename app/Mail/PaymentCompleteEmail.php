<?php

namespace App\Mail;

use App\BankTransaction;
use App\CostControl;
use App\ItemDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentCompleteEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $payment = $this->data;
        $id = $payment->id;
        $itemDetails = ItemDetails::where('case_id', $id)->get();
        $paymentCostCenter = CostControl::where('case_id', $id)->get();
        $paymentBankTransactions = BankTransaction::where('case_id', $id)->get();

        return $this->subject('BFlow - Case: ' . $id . ' - Title: ' . $payment->title)
            ->from('bflow@beltonefinancial.com', 'BFlow')
            ->view('mails.payment_complete_request',
                compact('payment', 'itemDetails', 'paymentCostCenter', 'paymentBankTransactions'));
    }
}
