<?php

namespace App\Http\Controllers;

use App\domain\model\PaymentMethodType;
use App\domaine\model\Currency;
use App\domaine\model\EWallet;
use App\domaine\model\Holder;
use App\domaine\model\MobileBillerCreditAccount;
use App\domaine\model\MobileBillerCreditAccountTransaction;
use App\domaine\model\TransactionDetail;
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
            $mobileBillerCreditAccount = new MobileBillerCreditAccount($uuid, $uuid, $data['userid'], 0, '', "MOBILEBILLERCM", TRUE, 'b28871c4-bf04-11e8-a52c-ac2b6ee888a2');

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

        $foundTransactions = MobileBillerCreditAccountTransaction::where('mobilebillercreditaccount', '=', $holder->mobilebillercreditaccount)->
            where('user_transaction_number', '=', (int)$request->get('user_transaction_number'))->get();
        if (!(count($foundTransactions) === 0)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Duplicated Transaction ID'), 200);
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
        $mbas = MobileBillerCreditAccount::where('b_id', '=', $holder->mobilebillercreditaccount)->get();

        if (!(count($mbas) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
        }

        $mba  = $mbas[0];

        if (!$mba->isPossible($transactionType, $amount)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Insufficient Amount'), 200);
        }



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
                $savedTransaction->accountstate = json_encode($mobilebillercreditaccount, JSON_UNESCAPED_SLASHES);
                $mobilebillercreditaccount->makeOperation($transactionType, $amount);

                $mobilebillercreditaccount->save();
                $savedTransaction->save();
                DB::commit();

                return response(array('success' => 1, 'faillure' => 0, 'response' => $returnedString), 200);
            } catch (\Exception $e) {

                DB::rollback();
                return response(array('success' => 0, 'faillure' => 1, 'raison' => "Something went wrong: " . $e->getMessage()), 200);

            }


        }
    }

    public function makeTopup(Request $request){

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string|min:1', //Moyen pour prelevement peut etre: Mobile Money,  Carte de credit, Cash, MobileBiller credit account

        ]);

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        //1-  Payment metod

        $paymentMethods = PaymentMethodType::where('b_id', '=', $request->get('payment_method_id'))->get();
        if (!(count($paymentMethods) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Payment method not found'), 200);
        }

        $paymentMethod = $paymentMethods[0];

        if ($paymentMethod->type === 'CREDITCARD'){
            $validator = Validator::make($request->all(), [
                'beneficiary' => 'required|string|min:1|max:150',
                'userid' => 'required|string|min:1|max:150',
                'amount' => 'required|numeric|min:1',
                'payment_method_id' => 'required|string|min:1', //Moyen pour prelevement peut etre: Mobile Money,  Carte de credit, Cash, MobileBiller credit account
                'card_number' => 'required|string|min:1',
                'card_holder' => 'required|string|min:1',
                'expiry_date'=> 'required|string|min:1|max:10',
                'security_code'=> 'required|string|min:3|max:3',
                'user_transaction_number' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }
        }elseif ($paymentMethod->type === 'MOBILEMONEY'){
            $validator = Validator::make($request->all(), [
                'beneficiary' => 'required|string|min:1|max:150',
                'userid' => 'required|string|min:1|max:150',
                'amount' => 'required|numeric|min:1',
                'card_number' => 'required|string|min:1',
                'card_holder' => 'required|string|min:1',
                'payment_method_id' => 'required|string|min:1', //Moyen pour prelevement peut etre: Mobile Money,  Carte de credit, Cash, MobileBiller credit account
                'user_transaction_number' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }
        }else{
            $validator = Validator::make($request->all(), [
                'beneficiary' => 'required|string|min:1|max:150',
                'userid' => 'required|string|min:1|max:150',
                'amount' => 'required|numeric|min:1',
                'payment_method_id' => 'required|string|min:1', //Moyen pour prelevement peut etre: Mobile Money,  Carte de credit, Cash, MobileBiller credit account
                'user_transaction_number' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }
        }

        $api = $paymentMethod->api;
        $apiObject = json_decode($api);
        $url = $apiObject->paymentUrl;//'https://jsonplaceholder.typicode.com/posts'; //Todo $api->paymentUrl;

        //2- Amount
        $amount = (float)$request->get('amount');

        // 3- Type de transaction
        $transactionTypes = TransactionType::where('name', '=', 'TOPUP')->get();
        if (!(count($transactionTypes) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Transaction Type not found'), 200);
        }

        $transactionType = $transactionTypes[0];

        //4- Users (Celui qui effectue l'operation)

        $holders = Holder::where('b_id', '=', $request->get('userid'))->get();

        if (!(count($holders) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User not found'), 200);
        }

        $holder = $holders[0];

        //5- Beneficiaire

        $beneficiaries = Holder::where('b_id', '=', $request->get('beneficiary'))->get();

        if (!(count($beneficiaries) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Beneficiary not found'), 200);
        }

        $beneficiary = $beneficiaries[0];

        //6- user_transaction_number verification de la duplication de numero de transaction

        $foundTransactions = MobileBillerCreditAccountTransaction::where('mobilebillercreditaccount', '=', $holder->mobilebillercreditaccount)->
        where('user_transaction_number', '=', (int)$request->get('user_transaction_number'))->get();
        if (!(count($foundTransactions) === 0)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Duplicated Transaction ID'), 200);
        }

        //7- Preparation de la transaction en vue d'empecher le replay de la numerotation des transaction par le client

        $uuid = Uuid::generate()->string;
        $now = date("Y-m-d H:i:s");

        $card_hoder = ($request->get('card_holder') == null)? $holder->firstname . ' ' . $holder->lastname:$request->get('card_holder');

        $mobileBillerAccountTransaction = new MobileBillerCreditAccountTransaction($uuid, $now, $holder->mobilebillercreditaccount, $card_hoder,
            $amount, $transactionType->b_id, null, $request->get('user_transaction_number'), MobileBillerCreditAccountTransaction::PENDING, '');

        $failed = false;
        $returnedString = '';

        $client = new Client();
        $mobileBillerAccountTransaction->save();

        //8- Compte du beneficiaire
        $mbas = MobileBillerCreditAccount::where('b_id', '=', $beneficiary->mobilebillercreditaccount)->get();

        if (!(count($mbas) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
        }

        $mba  = $mbas[0];

        if (!$mba->isPossible($transactionType, $amount)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Insufficient Amount'), 200);
        }


        //9- Transfere de fond
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
                //10- Apres transfere de fond

                DB::beginTransaction();

                $transactionDetails = new TransactionDetail($holder->b_id,$request->get('card_number'),$card_hoder,
                    $request->get('security_code'), $request->get('payment_method_id'), $beneficiary->b_id, $transactionType->b_id);

                $savedTransaction = MobileBillerCreditAccountTransaction::where('b_id', '=', $uuid)->get()[0];

                $isjson = $this->is_JSON($returnedString);
                if ($failed === true or !($isjson == 0)) {

                    //11- Echec de transfere

                    $savedTransaction->state = MobileBillerCreditAccountTransaction::FAILED;
                    $savedTransaction->returned_result = $returnedString;
                    $savedTransaction->transaction_details = json_encode($transactionDetails, JSON_UNESCAPED_SLASHES);
                    $savedTransaction->save();
                    DB::commit();
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $returnedString), 200);
                }

                //12- Succes du transfere.

                $savedTransaction->state = MobileBillerCreditAccountTransaction::SUCCESS;
                $savedTransaction->returned_result = $returnedString;

                $mobilebillercreditaccount = MobileBillerCreditAccount::where('b_id', '=', $beneficiary->mobilebillercreditaccount)->get()[0];
                $savedTransaction->accountstate = json_encode($mobilebillercreditaccount, JSON_UNESCAPED_SLASHES);
                $savedTransaction->transaction_details = json_encode($transactionDetails, JSON_UNESCAPED_SLASHES);
                $mobilebillercreditaccount->makeOperation($transactionType, $amount);

                $mobilebillercreditaccount->save();
                $savedTransaction->save();
                DB::commit();

                return response(array('success' => 1, 'faillure' => 0, 'response' => $returnedString), 200);
            } catch (\Exception $e) {

                DB::rollback();
                return response(array('success' => 0, 'faillure' => 1, 'raison' => "Something went wrong: " . $e->getMessage()), 200);

            }


        }
    }

    public function changePhoto(Request $request)
    {

    }

    public function getInfos(Request $request, $userId){
        //return $userId;
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1|max:150',
        ]);

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        if ($request->get('query') == 'balance'){

            $holders = Holder::where('username', '=', $userId)->orWhere('email', '=', $userId)->orWhere('b_id', '=', $userId)->get();

            if (!(count($holders) === 1)) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User not found'), 200);
            }

            $holder = $holders[0];
            //return $holder;
            $mbas = MobileBillerCreditAccount::where('b_id', '=', $holder->mobilebillercreditaccount)->get();

            if (!(count($mbas) === 1)){
                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
            }

            $mba  = $mbas[0];

            //return $mba;
            $currencies = Currency::where('b_id', '=', $mba->currency)->get();
            if (!(count($currencies) === 1)){
                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Currency Not found'), 200);
            }

            //sleep(3);
           // return $mba->balance . $currencies[0]->name;

            return response(array('success' => 1, 'faillure' => 0, 'response' => $mba->balance . $currencies[0]->name), 200);
        }else{
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Unknown operation'), 200);
        }


    }

    public function getPaymentmethodTypes(Request $request){
        return response(array('success' => 1, 'faillure' => 0, 'response' => PaymentMethodType::all()), 200);
    }

    public function makeTransfert(Request $request){
        $validator = Validator::make($request->all(), [
            'userid' => 'required|string|min:1|max:150',
            'beneficiary' => 'required|string|min:1|max:150',
            'amount' => 'required|numeric|min:100',
            'user_transaction_number' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return Redirect::back()->with('message',array('receiveResultStatusCode' => 200,
                'result'=>array('success'=>0, 'faillure'=>1, 'raison'=>$validator->errors()->first())));
        }

        //1- Amount
        $amount = (float)$request->get('amount');

        //2- Users (Celui qui effectue l'operation) represente le compte de depart

        $sourceHolders = Holder::where('b_id', '=', $request->get('userid'))->get();

        if (!(count($sourceHolders) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User not found'), 200);
        }

        $sourceHolder = $sourceHolders[0];


        //3- Compte source
        $sourceAcounts = MobileBillerCreditAccount::where('b_id', '=', $sourceHolder->mobilebillercreditaccount)->get();

        if (!(count($sourceAcounts) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
        }

        $sourceAcount  = $sourceAcounts[0];

        //4- user_transaction_number verification de la duplication de numero de transaction

        $foundTransactions = MobileBillerCreditAccountTransaction::where('mobilebillercreditaccount', '=', $sourceHolder->mobilebillercreditaccount)->
        where('user_transaction_number', '=', (int)$request->get('user_transaction_number'))->get();
        if (!(count($foundTransactions) === 0)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Duplicated Transaction ID'), 200);
        }



        //5- Destination holder (Celui qui beneficie)

        $destinationHolders = Holder::where('b_id', '=', $request->get('beneficiary'))->get();

        if (!(count($destinationHolders) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Beneficiary not found'), 200);
        }

        $destinationHolder = $destinationHolders[0];

        //6- Compte destination
        $destinationAcounts = MobileBillerCreditAccount::where('b_id', '=', $destinationHolder->mobilebillercreditaccount)->get();

        if (!(count($destinationAcounts) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User Mobile Biller Account not found'), 200);
        }

        $destinationAcount  = $destinationAcounts[0];



        //7- Verification de la disponibilite

        // Type de transaction
        $srcTransactionTypes = TransactionType::where('name', '=', 'TRANSFERT')->get();
        if (!(count($srcTransactionTypes) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Transaction Type not found'), 200);
        }

        $srcTransactionType = $srcTransactionTypes[0];

        if (!$sourceAcount->isPossible($srcTransactionType, $amount)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Insufficient Amount'), 200);
        }

        $destTransactionTypes = TransactionType::where('name', '=', 'DEPOSIT')->get();
        if (!(count($destTransactionTypes) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Transaction Type not found'), 200);
        }

        $destTransactionType = $destTransactionTypes[0];

        if (!$destinationAcount->isPossible($destTransactionType, $amount)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Depot impossible. Veuillez contacter votre gestionaire'), 200);
        }

        //8- Preparation de la transaction en vue d'empecher le replay de la numerotation des transaction par le client

        $uuid_src = Uuid::generate()->string;
        $now = date("Y-m-d H:i:s");

        $card_hoder_src = $sourceHolder->firstname . ' ' . $sourceHolder->lastname;

        $transactionDetails =  new TransactionDetail($sourceHolder->b_id, $sourceHolder->mobilebillercreditaccount, $card_hoder_src,
            '','MOBILEBILLER', $destinationAcount->b_id, $srcTransactionType); //($made_by, $account_number, $account_holder, $account_security_code, $account_type, $beneficiary, $transactionType)

        $mobileBillerAccountTransaction = new MobileBillerCreditAccountTransaction($uuid_src, $now, $sourceHolder->mobilebillercreditaccount, $card_hoder_src,
            $amount, $srcTransactionType->b_id, $transactionDetails , $request->get('user_transaction_number'), MobileBillerCreditAccountTransaction::PENDING, '');

        $failed = false;
        $returnedString = '';
        $mobileBillerAccountTransaction->save();

        try {
            //19- Debut de la transaction

            DB::beginTransaction();

            $src_json = json_encode($sourceAcount, JSON_UNESCAPED_SLASHES); //etat du compte source avant toute operation de debit.
            $sourceAcount->makeOperation($srcTransactionType, $amount);
            $destinationAcount->makeOperation($destTransactionType, $amount);
            $sourceAcount->save();
            $destinationAcount->save();
            //$now = date("Y-m-d H:i:s");
            $savedTransaction = MobileBillerCreditAccountTransaction::where('b_id', '=', $uuid_src)->get()[0];

            $savedTransaction->state = MobileBillerCreditAccountTransaction::SUCCESS;
            $savedTransaction->returned_result = 'Transfert effectue avec succes.';
            $savedTransaction->accountstate = $src_json;
            //$savedTransaction->transaction_details = json_encode($transactionDetails, JSON_UNESCAPED_SLASHES);
            $savedTransaction->save();

            $mobileBillerAccountTransaction_dest = new MobileBillerCreditAccountTransaction(Uuid::generate()->string, date("Y-m-d H:i:s"), $destinationHolder->mobilebillercreditaccount,
                $destinationHolder->firstname.'  ' . $destinationHolder->lastname,
                $amount, $destTransactionType->b_id, $transactionDetails , time(), MobileBillerCreditAccountTransaction::SUCCESS, 'Transfert effectue avec succes.');
            $mobileBillerAccountTransaction_dest->save();

            DB::commit();

            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Transfert effectue avec succes.'), 200);
        } catch (\Exception $e) {

            DB::rollback();
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Something went wrong: " . $e->getMessage()), 200);

        }

    }

    public function getTransactions(Request $request, $userid){
        $users = Holder::where('b_id', '=', $userid)->get();
        if (!(count($users) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'User not found'), 200);
        }
        $user = $users[0];

        return response(array('success' => 1, 'faillure' => 0,
            'response' => MobileBillerCreditAccountTransaction::where('mobilebillercreditaccount', '=', $user->mobilebillercreditaccount)->orderBy('created_at', 'DESC')->get()), 200);
    }

    public function getTransactionDetails(Request $request, $transactionid){
        $transactions = MobileBillerCreditAccountTransaction::where('b_id', '=', $transactionid)->get();
        if (!(count($transactions) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Transaction not found"), 200); 
        }

        $transaction = $transactions[0];

        $accounts = MobileBillerCreditAccount::where('b_id', '=', $transaction->mobilebillercreditaccount)->get();
        if (!(count($accounts) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whooof Something went wrong 1 "), 200); 
        }

        $transaction->mobilebillercreditaccount = $accounts[0];

        $transaction_types = TransactionType::where('b_id', '=', $transaction->transaction_type)->get();
        if (!(count($transaction_types) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whooof Something went wrong 2 "), 200); 
        }

        $transaction->transaction_type = $transaction_types[0];

        $transaction_details = json_decode($transaction->transaction_details);

        //$transaction_types

        $transaction->transaction_details = $transaction_details;

        $holderId = $transaction_details->made_by;

        $holders = Holder::where('b_id', '=', $holderId)->get();
        if (!(count($holders) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whooof Something went wrong 3 "), 200); 
        }

        $transaction->made_by = $holders[0];

        $beneficiariesAccounts = MobileBillerCreditAccount::where('b_id', '=', $transaction_details->beneficiary)->get();
        //return $beneficiaries;
        if (!(count($beneficiariesAccounts) === 1)) {
            //return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whooof Something went wrong 4 "), 200); 
            $transaction->beneficiary_account = null;
            $transaction->beneficiary = null;
        }else{

            $beneficiariesAccount = $beneficiariesAccounts[0];

        $transaction->beneficiary_account = $beneficiariesAccount;

        $beneficiaries = Holder::where('b_id', '=', $beneficiariesAccount->holder)->get();
        if (!(count($beneficiaries) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whooof Something went wrong 3 "), 200); 
        }

        $transaction->beneficiary = $beneficiaries[0];

        }

        

        return  response(array('success' => 1, 'faillure' => 0, 'response' => $transaction), 200); 

        /*
        ['b_id', 'date', 'mobilebillercreditaccount', 'made_by', 'amount', 'transaction_type',
        'transaction_details', 'user_transaction_number', 'state', 'returned_result', 'accountstate']


        {"made_by":"d2954170-6fec-11e8-9acc-69d6bf7ddf86","account_number":"7fa06bd0-bd8c-11e8-96fb-1d78c172ebec","account_holder":"Nkalla Ehawe Didier Junior","account_security_code":"","account_type":"MOBILEBILLER","beneficiary":"c44b8ff0-c247-11e8-a0fc-a15ce85837bd","transactionType":{"id":5,"b_id":"ffd70eaf-c2f6-11e8-a7b7-ac2b6ee888a2","code":"ffd70eaf-c2f6-11e8-a7b7-ac2b6ee888a2","name":"TRANSFERT","description":"Operation to transfert money from a Mobile Biller Account to Anothe Mobile Biller Acount","signe":"-","created_at":"2018-09-20 22:42:35","updated_at":"2018-09-20 22:42:35"}}
        */
    }
}
