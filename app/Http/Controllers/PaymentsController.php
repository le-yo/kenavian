<?php

namespace App\Http\Controllers;

use App\Jobs\PaymentReceived;
use App\Log;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function receiver(Request $request) {
        $input = $request->getContent();
        $xml = new \DOMDocument();
        $xml->loadXML($input);
        $data = ['slug'=>'mpesa_payment_received','content' => $input];
        Log::create($data);
        $payment = (new PaymentReceived($input))->delay(5);
        $this->dispatch($payment);
        $data = array();
        $data['code'] = 200;
        $data['message'] = "Payment received Successfully";
        return response()->json($data);
    }

}
