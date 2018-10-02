<?php

namespace App\Http\Middleware;

use App\Test;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

class TokenVerificationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {


       $scope = $request->get('scope');

       if ($scope === 'client'){

           return $next($request);

       }



        $client = new Client();

        try {




            $data = array('scope'=>$request->get('scope'));

            $response = $client->post(env('HOST_IDENTITY_AND_ACCESS').'/api/confirm-access-token', [
                'headers'=>[
                    'Authorization' =>  $request->header('Authorization')
                ],
                'body'=>json_encode($data)
            ]);



            $result = (string)$response->getBody();

            if(!($result === '1')){

                return response("Unauthorized to access  ------->  ".json_encode($request), 401);

            }


        } catch (BadResponseException $e) {

            return response("Something went wrong", 500);
        }

        return $next($request);

    }
}


