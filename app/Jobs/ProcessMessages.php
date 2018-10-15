<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ProcessMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected  $exchangeName;
    protected $exchangetype;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($exchangeName, $exchangetype, $message)
    {
        $this->exchangeName = $exchangeName;
        $this->exchangetype = $exchangetype;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->publishMessageToQueue($this->exchangeName, $this->exchangetype ,$this->message);
    }

    public function publishMessageToQueue($exchangeName, $exchangetype, $message){


        $connection = null;


        try {


            $connection = new AMQPStreamConnection(env('RABBIT_MQ_HOST'), env('RABBIT_MQ_PORT'), env('RABBIT_MQ_USER'), env('RABBIT_MQ_PASSWORD'));

            $channel = $connection->channel();

            $channel->exchange_declare($exchangeName, $exchangetype, false, true, false);


            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));

            $channel->basic_publish($msg, $exchangeName);
            $channel->close();

        } catch (\Exception $exception){


        }
        finally{

            if ($connection != null){
                $connection->close();
            }

        }

    }
}
