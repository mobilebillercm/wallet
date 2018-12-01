<?php

require_once __DIR__ . '/../../../vendor/autoload.php';


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use PhpAmqpLib\Connection\AMQPStreamConnection;


    $connection = new AMQPStreamConnection(
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_HOST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_PORT'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_USER'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_PASSWORD'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_VIRTUAL_HOST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_INSIST'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOGIN_METHOD'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOGIN_RESPONSE'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_LOCALE'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_CONNECTION_TIME_OUT'],
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_READ_WRITE_TIME_OUT'],
        null,
        false,
        parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBITMQ_HEARTBEAT']
    );

    $channel = $connection->channel();


    $channel->exchange_declare(
        parse_ini_file("global-var-config.ini", true)['EXCHANGES']['SERVICE_PAYEMENT_WITH_MOBILE_BILLER_CREDIT_ACCOUNT_INITIATED_EXCHANGE'],
        'fanout',
        false,
        true,
        false);

    list($queue_name, ,) = $channel->queue_declare(
        parse_ini_file("global-var-config.ini", true)['QUEUES']['WALLET_SERVICE_PAYEMENT_WITH_MOBILE_BILLER_CREDIT_ACCOUNT_INITIATED_QUEUE'],
        false,
        true,
        false,
        false);

    $channel->queue_bind(
        $queue_name,
        parse_ini_file("global-var-config.ini", true)['EXCHANGES']['SERVICE_PAYEMENT_WITH_MOBILE_BILLER_CREDIT_ACCOUNT_INITIATED_EXCHANGE']
    );

    echo " [*] Waiting for service payement with mobile credit account to be initiated user to exit press CTRL+C\n";




    $callback = function ($msg) {


        $client = new client();
        $token = null;



        try {

            $tokenUrl = parse_ini_file("global-var-config.ini", true)['URLS']['HOST_WALLET'] . '/oauth/token';


            $tokenData = $client->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_ID'],
                    'client_secret' => parse_ini_file("global-var-config.ini", true)['LOGINS']['RABBIT_MQ_CLIENT_SECRET'],
                ],
            ]);


            $token = json_decode((string)$tokenData->getBody());


        } catch (BadResponseException $e) {

            return $e->getMessage();

        }


        try {

            $url = parse_ini_file("global-var-config.ini", true)['URLS']['HOST_WALLET'] . '/api/payements-from-mobile-biller-credit-account';


            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => $token->access_token,
                ],
                'body' => $msg->body
            ]);

            echo $res->getBody();


        } catch (BadResponseException $e) {

            return $e->getMessage();
        }


    };


    $channel->basic_consume($queue_name, '', false, true, false, false, $callback);

    while (count($channel->callbacks)) {

        $channel->wait();
    }

    $channel->close();
    $connection->close();