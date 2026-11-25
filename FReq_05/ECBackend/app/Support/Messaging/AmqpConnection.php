<?php

namespace App\Support\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class AmqpConnection
{
    public static function open(): AMQPStreamConnection
    {
        $config = config('messaging.connection');

        return new AMQPStreamConnection(
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost'],
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            30.0,
            null,
            true,
            0
        );
    }
}
