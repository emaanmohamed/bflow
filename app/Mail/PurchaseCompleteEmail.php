<?php

namespace App\Mail;

use App\BankTransaction;
use App\CostControl;
use App\ItemDetails;
use App\PurchaseBLOCostCenter;
use App\PurchaseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PurchaseCompleteEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }


    public function build()
    {
        $purchase = $this->data;
        $id = $purchase->id;
        $itemDetails = ItemDetails::where('case_id', $id)->get();
        $purchaseCostCenter = CostControl::where('case_id', $id)->get();
        $purchaseBLOCostCenter = PurchaseBLOCostCenter::where('case_id', $id)->get();
        $purchaseBankTransactions = BankTransaction::where('case_id', $id)->get();

        return $this->subject('BFlow - Case: ' . $id . ' - Title: ' . $purchase->title)
            ->from('bflow@beltonefinancial.com', 'BFlow')
            ->view('mails.purchase_complete_request',
                compact('purchase', 'itemDetails', 'purchaseCostCenter', 'purchaseBLOCostCenter', 'purchaseBankTransactions'));
    }
}
