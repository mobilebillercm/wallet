<?php

namespace App\Http\Controllers;

use App\domain\model\PaymentMethodType;
use App\domaine\model\EWallet;
use App\domaine\model\Holder;
use App\domaine\model\MobileBillerCreditAccount;
use App\domaine\model\MobileBillerCreditAccountTransaction;
use App\domaine\model\TransactionType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class ApiController extends Controller
{

    public function is_JSON($args)
    {
        json_decode($args);
        return (json_last_error());
    }

    public function createHolder()
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        $validator = Validator::make(
            [
                'userid' => $data['userid'],
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'enablement' => $data['enablement'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ],
            [
                'userid' => 'required|string|min:1|max:150',
                'firstname' => 'required|string|min:1|max:250',
                'lastname' => 'required|string|min:1|max:250',
                'enablement' => 'required|numeric|min:0|max:1',
                'username' => 'required|email|min:1|max:250',
                'email' => 'required|email|min:1|max:250',
                'phone' => ['required', 'regex:/^(22|23|24|67|69|65|68|66)[0-9]{7}$/'],
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        DB::beginTransaction();

        try {

            $b_id = Uuid::generate()->string;
            $ewallet = new EWallet($b_id, $data['userid'], '[]');

            $uuid = Uuid::generate()->string;
            $mobileBillerCreditAccount = new MobileBillerCreditAccount($uuid, $uuid, $data['userid'], 0, '', "MOBILEBILLERCM", TRUE);

            $ewallet->addAccounts([$uuid]);

            $holder = new Holder($data['userid'], $data['firstname'], $data['lastname'], $data['enablement'], $data['username'], $data['email'], $data['phone'], $b_id, $uuid);

            $mobileBillerCreditAccount->save();
            $ewallet->save();
            $holder->save();

        } catch (\Exception $e) {

            DB::rollback();

            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Something went wrong: "), 200);

        }

        DB::commit();

        return response(array('success' => 1, 'faillure' => 0, 'response' => "Successfully created"), 200);

    }




    public function makeOperation(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'transaction_type' => 'required|string|min:1|max:150',
            'userid' => 'required|string|min:1|max:150',
            'amount' => 'required|numeric|min:1',
            'payment_method_id' => 'required|string|min:1',
            'card_number' => 'required|string|min:1',
            'card_holder' => 'required|string|min:1',
            'user_transaction_number' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        // Payment metod

        $paymentMethods = PaymentMethodType::where('b_id', '=', $request->get('payment_method_id'))->get();
        if (!(count($paymentMethods) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Payment method not found'), 200);
        }

        $paymentMethod = $paymentMethods[0];
        $api = $paymentMethod->api;
        $url = 'https://jsonplaceholder.typicode.com/posts'; //Todo $api->paymentUrl;

        $amount = (float)$request->get('amount');


        $transactionTypes = TransactionType::where('b_id', '=', $request->get('transaction_type'))->get();
        if (!(count($transactionTypes) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Transaction Type not found'), 200);
        }

        $transactionType = $transactionTypes[0];

        $holders = Holder::where('b_id', '=', $request->get('userid'))->get();

        if (!(count($holders) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User not found'), 200);
        }

        $holder = $holders[0];

        $mbas = MobileBillerCreditAccount::where('b_id', '=', $holder->mobilebillercreditaccount)->get();

        if (!(count($mbas) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
        }

        $mba  = $mbas[0];

        if (!$mba->isPossible($transactionType, $amount)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Insufficient Amount'), 200);
        }

        $uuid = Uuid::generate()->string;
        $now = date("Y-m-d H:i:s");

        $mobileBillerAccountTransaction = new MobileBillerCreditAccountTransaction($uuid, $now, $holder->mobilebillercreditaccount, $request->get('card_holder'),
            $amount, $transactionType->b_id, null, $request->get('user_transaction_number'), MobileBillerCreditAccountTransaction::PENDING, '');

        $failed = false;
        $returnedString = '';

        $client = new Client();
        $mobileBillerAccountTransaction->save();
        //return $mobileBillerAccountTransaction;
        try {

            $params = array('title' => $request->get('userid'), 'body' => "$amount" . " | " . $request->get('card_number'), 'userId' => 1);

            $response = $client->post($url, [
                'headers' => [
                    "Content-type" => "application/json; charset=UTF-8",
                ],
                'body' => json_encode($params)
            ]);

            //return (string)$response->getBody();
            $returnedString = (string)$response->getBody();

            $failed = false;

        } catch (BadResponseException $e) {
            $failed = true;
            $returnedString = $e->getMessage();
            //return response(array('success'=>0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
        } finally {
            try {
                DB::beginTransaction();

                $savedTransaction = MobileBillerCreditAccountTransaction::where('b_id', '=', $uuid)->get()[0];

                $isjson = $this->is_JSON($returnedString);
                if ($failed === true or !($isjson == 0)) {
                    $savedTransaction->state = MobileBillerCreditAccountTransaction::FAILED;
                    $savedTransaction->returned_result = $returnedString;
                    $savedTransaction->save();
                    DB::commit();
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $returnedString), 200);
                }

                $savedTransaction->state = MobileBillerCreditAccountTransaction::SUCCESS;
                $savedTransaction->returned_result = $returnedString;

                $mobilebillercreditaccount = MobileBillerCreditAccount::where('b_id', '=', $savedTransaction->mobilebillercreditaccount)->get()[0];
                $mobilebillercreditaccount->makeOperation($transactionType, $amount);
                $mobilebillercreditaccount->save();
                $savedTransaction->save();

                DB::commit();

                return response(array('success' => 1, 'faillure' => 0, 'response' => $returnedString), 200);
            } catch (\Exception $e) {

                DB::rollback();
                return response(array('success' => 0, 'faillure' => 1, 'raison' => "Something went wrong: "), 200);

            }


        }
    }

    public function changePhoto(Request $request)
    {

    }
}
