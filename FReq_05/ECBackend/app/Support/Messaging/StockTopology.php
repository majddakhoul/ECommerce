<?php

namespace App\Support\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class StockTopology
{
    public static function declare(AMQPChannel $channel): void
    {
        $config = config('messaging.stock');

        $channel->exchange_declare($config['exchange'], 'topic', false, true, false);
        $channel->exchange_declare($config['dead_letter_exchange'], 'direct', false, true, false);

        $channel->queue_declare($config['dead_letter_queue'], false, true, false, false);
        $channel->queue_bind($config['dead_letter_queue'], $config['dead_letter_exchange'], $config['dead_letter_routing_key']);

        $channel->queue_declare($config['queue'], false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $config['dead_letter_exchange'],
            'x-dead-letter-routing-key' => $config['dead_letter_routing_key'],
        ]));
        $channel->queue_bind($config['queue'], $config['exchange'], $config['routing_key']);
    }

    public static function declareJobFailures(AMQPChannel $channel): void
    {
        $exchange = (string) config('messaging.jobs.failed_exchange');
        $pattern = (string) config('messaging.jobs.failed_routing_key');

        $channel->exchange_declare($exchange, 'direct', false, true, false);

        foreach (config('messaging.queues') as $queue) {
            $failedQueue = $queue . '.failed';

            $channel->queue_declare($failedQueue, false, true, false, false);
            $channel->queue_bind($failedQueue, $exchange, sprintf($pattern, $queue));

            $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
                'x-dead-letter-exchange' => $exchange,
                'x-dead-letter-routing-key' => sprintf($pattern, $queue),
            ]));
        }
    }
}
