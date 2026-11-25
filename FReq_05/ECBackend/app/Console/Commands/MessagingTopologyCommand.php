<?php

namespace App\Console\Commands;

use App\Support\Messaging\AmqpConnection;
use App\Support\Messaging\StockTopology;
use Illuminate\Console\Command;

class MessagingTopologyCommand extends Command
{
    protected $signature = 'messaging:topology';

    protected $description = 'Declare the RabbitMQ exchanges, queues and dead letter queues used by the application';

    public function handle(): int
    {
        $connection = AmqpConnection::open();
        $channel = $connection->channel();

        StockTopology::declare($channel);
        StockTopology::declareJobFailures($channel);

        $channel->close();
        $connection->close();

        $this->info('RabbitMQ topology declared.');

        return self::SUCCESS;
    }
}
