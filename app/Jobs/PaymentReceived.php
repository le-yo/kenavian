<?php

namespace App\Jobs;

use App\Account;
use App\Http\Controllers\ValidatePhoneController;
use App\Log;
use App\Payment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Msisdn\Utility;

class PaymentReceived extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $payload;

    /**
     * Create a new job instance.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Load the xml to the data variable
        $data = ['slug'=>'mpesa_payment_received','content' => $this->payload];
        $log = new Log();
        $log->slug = 'mpesa_payment_received';
        $log->content = $this->payload;
        $log->save();
        $xml = new \DOMDocument();
        $xml -> loadXML($this->payload);

        // Strip the xml and store them in the data variable
        $data['phone'] = "+254".substr(trim($xml->getElementsByTagName('MSISDN')->item(0)->nodeValue), -9);
        if($xml->getElementsByTagName('KYCInfo')->length == 2) {
            $data['client_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue.' '.$xml->getElementsByTagName('KYCValue')->item(1)->nodeValue;
        } elseif($xml->getElementsByTagName('KYCInfo')->length == 3) {
            $data['client_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue.' '.$xml->getElementsByTagName('KYCValue')->item(1)->nodeValue.' '.$xml->getElementsByTagName('KYCValue')->item(2)->nodeValue;
        }
        $data['transaction_id'] = $xml->getElementsByTagName('TransID')->item(0)->nodeValue;
        $data['amount'] = $xml->getElementsByTagName('TransAmount')->item(0)->nodeValue;
        $data['account_no'] = $xml->getElementsByTagName('BillRefNumber')->item(0)->nodeValue;
        $data['transaction_time'] = $xml->getElementsByTagName('TransTime')->item(0)->nodeValue;
        $data['paybill'] = $xml->getElementsByTagName('BusinessShortCode')->item(0)->nodeValue;
        $data['orgaccountbalance'] = $xml->getElementsByTagName('OrgAccountBalance')->item(0)->nodeValue;

        // Check wether the transaction exists
        $transaction = Payment::whereTransactionId($data['transaction_id'])->first();

        if($transaction == null) {
            $payment = Payment::create($data);
            //topup account
            $Acc = Account::wherePhone($data['phone'])->first();
            if(!$Acc){
                $Acc = new Account();
                $Acc->balance = 0;
                $Acc->phone = $data['phone'];
                $Acc->name = $data['client_name'];
            }
            $Acc->balance = $Acc->balance + $data['amount'];
            $Acc->save();
            $log->ref = 'account_top_up_to_'.$Acc->id;
            $log->save();
            $response = self::purchaseAirtime($data);
            if($response->status!=200) {
                $data['account_no'] = "SAF".$data['account_no'];
                $response = self::purchaseAirtime($data);
            }
            if($response->status!=200) {
                $data['account_no'] = "SAF".$data['phone'];
                $response = self::purchaseAirtime($data);
            }
                if($response->status==200) {
                    $Acc->balance = $Acc->balance - $data['amount'];
                    $Acc->save();
                }
        }
    }


    /**
     * Decodes jsonp responses
     *
     * @param $jsonp
     * @param bool $assoc
     * @return mixed
     */
    public function jsonp_decode($jsonp, $assoc = false) {
        if($jsonp !== '[' && $jsonp !== '{') {
            $jsonp = substr($jsonp, strpos($jsonp, '('));
        }
        return json_decode(trim($jsonp,'();'), $assoc);
    }

    public function purchaseAirtime($payment_data){
        //sanitize account:
        $payment_data['account_no'] = trim(str_replace(" ","",$payment_data['account_no']));
        $url = env('airtime_endpoint');
        $data = array();
        $data['Credentials'] = [
            'merchantCode'=>env('airtime_merchantCode'),
            'username'=>env('airtime_username'),
            'password'=>env('airtime_password'),
        ];

        $services = [
            "101"=>[
                "serviceID"=>101,
                "serviceCode"=>"SAFCOM"
            ],
            "102"=>[
                "serviceID"=>102,
                "serviceCode"=>"AIRTEL"
            ],
            "103"=>[
                "serviceID"=>103,
                "serviceCode"=>"TELKOM"
            ],
            "105"=>[
                "serviceID"=>105,
                "serviceCode"=>"KPLCPREPAID"
            ],
        ];

        $telco = ValidatePhoneController::channel($payment_data['account_no']);

        if($telco == 'AIRTEL' || strtolower(substr($payment_data['account_no'],0,3))=='air'){
            $commission_kenavian = 0.06*$payment_data['amount'];
            $commission_yangu = 0.02*$payment_data['amount'];
            $serviceId = $services['102']['serviceID'];
            $serviceCode = $services['102']['serviceCode'];
            $phone = "254".substr(trim($payment_data['account_no']),-9);
        }elseif($telco == 'TELKOM' || strtolower(substr($payment_data['account_no'],0,3))=='tel'){
            $commission_kenavian = 0.05*$payment_data['amount'];
            $commission_yangu = 0.02*$payment_data['amount'];
            $serviceId = $services['103']['serviceID'];
            $serviceCode = $services['103']['serviceCode'];
            $phone = "254".substr(trim($payment_data['account_no']),-9);
        }elseif(strtolower(substr($payment_data['account_no'],0,5))=='TOKEN' || strtolower(substr($payment_data['account_no'],0,3))=='tok'){
            $commission_kenavian = 0.014*$payment_data['amount'];
            $commission_yangu = 0.005*$payment_data['amount'];
            $serviceId = $services['105']['serviceID'];
            $serviceCode = $services['105']['serviceCode'];
            $phone = substr(trim($payment_data['account_no']),5);
        }elseif(strtolower(substr($payment_data['account_no'],0,4))=='KPLC'){
            $commission_kenavian = 0.014*$payment_data['amount'];
            $commission_yangu = 0.005*$payment_data['amount'];
            $serviceId = $services['105']['serviceID'];
            $serviceCode = $services['105']['serviceCode'];
            $phone = substr(trim($payment_data['account_no']),4);
        }else{
            $commission_kenavian = 0.04*$payment_data['amount'];
            $commission_yangu = 0.02*$payment_data['amount'];
            $serviceId = $services['101']['serviceID'];
            $serviceCode = $services['101']['serviceCode'];
            $phone = "254".substr(trim($payment_data['account_no']),-9);
        }


        $data['Request'] = [
            "transactionRef"=>"kenavian_".$payment_data['transaction_id']."_".rand(1,200),
            "serviceID"=>$serviceId,
            "serviceCode"=>$serviceCode,
            "msisdn"=>$phone,
            "accountNumber"=>$phone,
            "amountPaid"=>$payment_data['amount'],
        ];


        $logdata = ['slug' => 'airtime_post_request', 'content' => json_encode($data)];
        //log request
        Log::create($logdata);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data)))
        );
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($ch);
        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            echo "cURL error ({$errno}):\n {$error_message}";
        }
        curl_close($ch);

        $dt = ['slug' => 'airtime_post_response', 'content' => $data];

        //log response
        Log::create($dt);
        $response = json_decode($data);
        if($response->status==200) {
            //add commission
            $Acc = Account::wherePhone('yangu')->first();
            if(!$Acc){
                $Acc = new Account();
                $Acc->balance = 0;
                $Acc->phone = 'yangu';
                $Acc->name = 'yangu';
            }
            $Acc->balance = $Acc->balance + $commission_yangu;
            $Acc->save();

            $Acc = Account::wherePhone('kenavian')->first();
            if(!$Acc){
                $Acc = new Account();
                $Acc->balance = 0;
                $Acc->phone = 'kenavian';
                $Acc->name = 'kenavian';
            }
            $Acc->balance = $Acc->balance + $commission_kenavian;
            $Acc->save();
        }
        return $response;
    }
}
