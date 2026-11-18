<?php

namespace App\Console\Commands;

use App\Services\Realtime\StockEventHandler;
use App\Support\Messaging\AmqpConnection;
use App\Support\Messaging\StockTopology;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class ConsumeStockEventsCommand extends Command
{
    protected $signature = 'inventory:consume {--max-messages=0 : Stop after this many messages, 0 means run forever}';

    protected $description = 'Consume stock.changed events published by the inventory service and broadcast them over WebSocket';

    private bool $stopping = false;

    private int $processed = 0;

    public function handle(StockEventHandler $handler): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->stopping = true);
            pcntl_signal(SIGINT, fn () => $this->stopping = true);
        }

        $limit = (int) $this->option('max-messages');

        while (!$this->stopping) {
            try {
                $this->consume($handler, $limit);

                if ($limit > 0 && $this->processed >= $limit) {
                    break;
                }
            } catch (Throwable $e) {
                $this->error('Consumer lost its connection: ' . $e->getMessage());
                sleep(5);
            }
        }

        return self::SUCCESS;
    }

    private function consume(StockEventHandler $handler, int $limit): void
    {
        $config = config('messaging.stock');
        $connection = AmqpConnection::open();
        $channel = $connection->channel();

        StockTopology::declare($channel);
        $channel->basic_qos(0, $config['prefetch'], false);

        $channel->basic_consume($config['queue'], '', false, false, false, false, function (AMQPMessage $message) use ($handler, $channel, $config) {
            $this->process($handler, $channel, $message, $config);
        });

        $this->info("Consuming {$config['queue']}");

        while ($channel->is_consuming() && !$this->stopping) {
            if ($limit > 0 && $this->processed >= $limit) {
                break;
            }

            try {
                $channel->wait(null, false, 5);
            } catch (AMQPTimeoutException) {
            }
        }

        $channel->close();
        $connection->close();
    }

    private function process(StockEventHandler $handler, AMQPChannel $channel, AMQPMessage $message, array $config): void
    {
        try {
            $applied = $handler->handle($message->getBody());
            $message->ack();
            $this->processed++;
            $this->line($applied ? 'Broadcast stock event' : 'Skipped duplicate stock event');
        } catch (InvalidArgumentException $e) {
            $this->warn('Rejected malformed stock event: ' . $e->getMessage());
            $message->nack(false);
        } catch (Throwable $e) {
            $attempts = $this->attempts($message) + 1;

            if ($attempts >= $config['max_attempts']) {
                $this->error("Stock event moved to the dead letter queue after {$attempts} attempts: " . $e->getMessage());
                $message->nack(false);

                return;
            }

            $this->warn("Stock event failed, retrying ({$attempts}): " . $e->getMessage());
            sleep($attempts);

            $channel->basic_publish(new AMQPMessage($message->getBody(), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable(['x-attempts' => $attempts]),
            ]), $config['exchange'], $config['routing_key']);

            $message->ack();
        }
    }

    private function attempts(AMQPMessage $message): int
    {
        if (!$message->has('application_headers')) {
            return 0;
        }

        $headers = $message->get('application_headers')->getNativeData();

        return (int) ($headers['x-attempts'] ?? 0);
    }
}
