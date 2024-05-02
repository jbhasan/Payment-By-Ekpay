<?php

namespace Sayeed\PaymentByEkpay\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Sayeed\PaymentByEkpay\Library\SslCommerz\SslCommerzNotification;
use Illuminate\Support\Facades\DB;

class EkpayController extends Controller
{
	public function paymentResponse(Request $request, $response_type) {
		if ($response_type == 'success') {
			$response = $this->success($request);
			return redirect(url(config('ekpay.application_success_url') . '?data='.base64_encode(json_encode($response))));
		} elseif ($response_type == 'failed') {
			$response = $this->fail($request);
			return redirect(url(config('ekpay.application_failed_url') . '?data='.base64_encode(json_encode($response))));
		} else {
			$response = $this->cancel($request);
			return redirect(url(config('ekpay.application_cancel_url') . '?data='.base64_encode(json_encode($response))));
		}
	}

    public function index(Request $request)
    {
		$requested_data = $request->all();
        # Here you have to receive all the order data to initate the payment.
        # Let's say, your oder transaction informations are saving in a table called "ekpay_orders"
        # In "ekpay_orders" table, order unique identity is "transaction_id". "status" field contain status of the transaction, "amount" is the order amount to be paid and "currency" is for storing Site Currency which will be checked with paid currency.

        $post_data = $requested_data;
        # MERCHANT INFORMATION
        $post_data['mer_info']['mer_reg_id'] = config('ekpay.credentials.merchant_id');
        $post_data['mer_info']['mer_pas_key'] = config('ekpay.credentials.merchant_password');

        $post_data['req_timestamp'] = date('Y-m-d H:i:s') . " GMT+6";
        $post_data['mac_addr'] = config('ekpay.credentials.whitelisted_ip');
		
		# FEEDBACK INFORMATION
        $post_data['feed_uri']['s_uri'] = url('ekapy/payment-response/success'); //url(config('ekpay.application_success_url') ?? $requested_data['success_url']);
        $post_data['feed_uri']['f_uri'] = url('ekapy/payment-response/failed'); //url(config('ekpay.application_failed_url') ?? $requested_data['failed_url']);
        $post_data['feed_uri']['c_uri'] = url('ekapy/payment-response/cancel'); //url(config('ekpay.application_cancel_url') ?? $requested_data['cancel_url']);
		
		# IPN INFORMATION
        $post_data['ipn_info']['ipn_channel'] = 1;
        $post_data['ipn_info']['ipn_email'] = config('ekpay.application_ipn_email');
        $post_data['ipn_info']['ipn_uri'] = url('ekapy/ipn');
		
		# CUSTOMER INFORMATION
        $post_data['cust_info']['cust_name'] = $requested_data['customer_name'];
        $post_data['cust_info']['cust_email'] = $requested_data['customer_email'];
        $post_data['cust_info']['cust_mail_addr'] = $requested_data['customer_address'] ?? 'Customer Address';
        $post_data['cust_info']['cust_country'] = $requested_data['customer_country'] ?? "Bangladesh";
        $post_data['cust_info']['cust_mobo_no'] = $requested_data['customer_mobile'];

		# TRANSACTION INFORMATION
		$rand_order_id = range(1000, 9999);
		shuffle($rand_order_id);
        $post_data['trns_info']['ord_det'] = $requested_data['product_name'] ?? "Digital Product";
        $post_data['trns_info']['ord_id'] = $requested_data['order_id'] ?? $rand_order_id[0];
        $post_data['trns_info']['trnx_amt'] = $requested_data['amount'];
        $post_data['trns_info']['trnx_currency'] = "BDT";
        $post_data['trns_info']['trnx_id'] = uniqid();

        #Before  going to initiate the payment order status need to insert or update as Pending.
        DB::table('ekpay_orders')
            ->where('transaction_id', $post_data['trns_info']['trnx_id'])
            ->updateOrInsert([
                'order_id' => $post_data['trns_info']['ord_id'],
                'name' => $post_data['cust_info']['cust_name'],
                'email' => $post_data['cust_info']['cust_email'],
                'phone' => $post_data['cust_info']['cust_mobo_no'],
                'amount' => $post_data['trns_info']['trnx_amt'],
                'status' => 'Pending',
                'address' => $post_data['cust_info']['cust_mail_addr'],
                'transaction_id' => $post_data['trns_info']['trnx_id'],
                'currency' => $post_data['trns_info']['trnx_currency'],
				'created_at' => date('Y-m-d H:i:s')
            ]);

		
        $payment_options = $this->callToApi(config('ekpay.urls.make_payment'), $post_data, ['Content-Type: application/json']);
		$payment_options = json_decode($payment_options);
		if($payment_options->msg_code == 1000) {
			$redirect_url = config('ekpay.api_domain') . '?sToken='.$payment_options->secure_token.'&trnsID='.$post_data['trns_info']['trnx_id'];
			return redirect($redirect_url);
		} else {
			dd($payment_options);
		}
    }

    private function success(Request $request)
    {
        $tran_id = $request->transId;

        #Check order status in order tabel against the transaction id or order id.
        $order_details = DB::table('ekpay_orders')->where('transaction_id', $tran_id)->select('transaction_id', 'status', 'currency', 'amount', 'created_at')->first();
        if ($order_details->status == 'Pending') {
			$validation = $this->orderValidate($tran_id, $order_details->created_at, $order_details->amount, $order_details->currency);
            if ($validation['status'] == true) {
                /*
                That means IPN did not work or IPN URL was not set in your merchant panel. Here you need to update order status
                in order table as Processing or Complete.
                Here you can also sent sms or email for successfull transaction to customer
                */
                DB::table('ekpay_orders')
                    ->where('transaction_id', $tran_id)
                    ->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Complete', 'response_data' => base64_encode(json_encode($validation['data']))]);
                return ["status" => "completed", 'transaction_id' => $tran_id, "message" => "Transaction is successfully Completed"];
            } else {
				DB::table('ekpay_orders')
                    ->where('transaction_id', $tran_id)
                    ->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Complete', 'response_data' => base64_encode(json_encode($validation['data']))]);
                return ["status" => "failed", 'transaction_id' => $tran_id, "message" => "Transaction is failed"];
			}
        } else if ($order_details->status == 'Processing' || $order_details->status == 'Complete') {
            /*
             That means through IPN Order status already updated. Now you can just show the customer that transaction is completed. No need to udate database.
             */
            return ["status" => "completed", 'transaction_id' => $tran_id, "message" => "Transaction is successfully Completed"];
        } else {
            #That means something wrong happened. You can redirect customer to your product page.
            return ["status" => "Invalid", 'transaction_id' => $tran_id, "message" => "Invalid Transaction"];
        }
    }

    private function fail(Request $request)
    {
        $tran_id = $request->transId;
        $order_details = DB::table('ekpay_orders')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount')->first();

        if ($order_details->status == 'Pending') {
            DB::table('ekpay_orders')
                ->where('transaction_id', $tran_id)
                ->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Failed', 'response_data' => base64_encode(json_encode($request->all()))]);
				return ["status" => "failed", 'transaction_id' => $tran_id, "message" => "Transaction is Failed"];
        } else if ($order_details->status == 'Processing' || $order_details->status == 'Complete') {
            return ["status" => "completed", 'transaction_id' => $tran_id, "message" => "Transaction is already Successful"];
        } else {
            return ["status" => "invalid", 'transaction_id' => $tran_id, "message" => "Transaction is Invalid"];
        }
    }

    private function cancel(Request $request)
    {
        $tran_id = $request->transId;
        $order_details = DB::table('ekpay_orders')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount')->first();

        if ($order_details->status == 'Pending') {
            DB::table('ekpay_orders')
                ->where('transaction_id', $tran_id)
                ->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Canceled', 'response_data' => base64_encode(json_encode($request->all()))]);
				return ["status" => "cancel", 'transaction_id' => $tran_id, "message" => "Transaction is Cancel"];
        } else if ($order_details->status == 'Processing' || $order_details->status == 'Complete') {
            return ["status" => "completed", 'transaction_id' => $tran_id, "message" =>  "Transaction is already Successful"];
        } else {
            return ["status" => "invalid", 'transaction_id' => $tran_id, "message" => "Transaction is Invalid"];
        }
    }

    public function ipn(Request $request)
    {
        #Received all the payement information from the gateway
		$tran_id = $request->transId;
        if ($tran_id) #Check transation id is posted or not.
        {
            #Check order status in order tabel against the transaction id or order id.
            $order_details = DB::table('ekpay_orders')
                ->where('transaction_id', $tran_id)
                ->select('transaction_id', 'status', 'currency', 'amount')->first();
            if ($order_details->status == 'Pending') {
                $validation = $this->orderValidate($tran_id, $order_details->created_at, $order_details->amount, $order_details->currency);
				if ($validation['status'] == true) {
					/*
					That means IPN did not work or IPN URL was not set in your merchant panel. Here you need to update order status
					in order table as Processing or Complete.
					Here you can also sent sms or email for successfull transaction to customer
					*/
					DB::table('ekpay_orders')
						->where('transaction_id', $tran_id)
						->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Complete', 'response_data' => base64_encode(json_encode($validation['data']))]);
					return ["status" => "completed", 'transaction_id' => $tran_id, "message" => "Transaction is successfully Completed"];
				} else {
					DB::table('ekpay_orders')
						->where('transaction_id', $tran_id)
						->update(['updated_at' => date('Y-m-d H:i:s'), 'status' => 'Complete', 'response_data' => base64_encode(json_encode($validation['data']))]);
					return ["status" => "failed", 'transaction_id' => $tran_id, "message" => "Transaction is failed"];
				}
            } else if ($order_details->status == 'Processing' || $order_details->status == 'Complete') {
                #That means Order status already updated. No need to udate database.
                return ["status" => "completed", 'transaction_id' => $tran_id, "message" => "Transaction is already successfully Completed"];
            } else {
                #That means something wrong happened. You can redirect customer to your product page.
                return ["status" => "invalid", 'transaction_id' => $tran_id, "message" => "Invalid Transaction"];
            }
        } else {
            return ["status" => "invalid", "message" => "Invalid Data"];
        }
    }

	private function orderValidate($tran_id, $datetime, $amount, $currency) {
		$post_data = [
			'trnx_id' => $tran_id,
			'trans_date' => date('Y-m-d', strtotime($datetime))
		];
		$payment_validation = $this->callToApi(config('ekpay.urls.transaction_status'), $post_data, ['Content-Type: application/json']);
		$payment_validation = json_decode($payment_validation);
		if($payment_validation->msg_code == 1020) {
			return ['status' => $payment_validation->trnx_info->trnx_amt == $amount && $payment_validation->trnx_info->curr == $currency, 'data' => $payment_validation];
		} else {
			return ['status' => false, 'data' => $payment_validation];
		}
	}
	private function callToApi($api_endpoint, $data, $header = [])
    {
		$api_url = config('ekpay.api_domain') . $api_endpoint;
        $curl = curl_init();

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // When the verify value is 0, the connection succeeds regardless of the names in the certificate.

        curl_setopt($curl, CURLOPT_URL, $api_url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrorNo = curl_errno($curl);
        curl_close($curl);


        if ($code == 200 & !($curlErrorNo)) {
            return $response;
        } else {
            return json_encode(['msg_code' => 9999, 'msg_det' => "FAILED TO CONNECT WITH EKPAY API"]);
            //return "cURL Error #:" . $err;
        }
    }

}
