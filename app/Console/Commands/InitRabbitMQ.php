<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class InitRabbitMQ extends Command
{
    protected $signature = 'rabbitmq:init';
    protected $description = 'Initialize RabbitMQ queues and exchanges';

    public function handle()
    {
        $this->info('Initializing RabbitMQ...');

        // Проверь
        echo "Default queue connection: " . config('queue.default') . "\n";
        echo "RabbitMQ queue name: " . config('queue.connections.rabbitmq.queue') . "\n";

        // Проверь что job использует правильную очередь
        $job = new \App\Jobs\ProcessTask('test');
        echo "Job queue: " . $job->queue . "\n";
        echo "Job connection: " . $job->connection . "\n";
        exit;

        $host = config('queue.connections.rabbitmq.hosts.0.host');
        $port = config('queue.connections.rabbitmq.hosts.0.port');
        $user = config('queue.connections.rabbitmq.hosts.0.user');
        $password = config('queue.connections.rabbitmq.hosts.0.password');
        $vhost = config('queue.connections.rabbitmq.hosts.0.vhost');
        $queue = config('queue.connections.rabbitmq.queue');
        $exchange = config('queue.connections.rabbitmq.exchange', 'laravel_exchange');

        try {
            // Подключаемся к RabbitMQ
            $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
            $channel = $connection->channel();

            // Создаем Exchange
            $this->info("Creating exchange: {$exchange}");
            $channel->exchange_declare(
                $exchange,
                'direct',
                false,  // passive
                true,   // durable
                false   // auto_delete
            );

            // Создаем очередь
            $this->info("Creating queue: {$queue}");
            $channel->queue_declare(
                $queue,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false,  // auto_delete
                false,  // nowait
                [
                    'x-max-priority' => ['I', 10] // Приоритеты
                ]
            );

            // Биндим очередь к exchange
            $this->info("Binding queue {$queue} to exchange {$exchange}");
            $channel->queue_bind($queue, $exchange, $queue);

            // Тестовое сообщение
            $this->info('Sending test message...');
            $message = new AMQPMessage(json_encode([
                'type' => 'test',
                'timestamp' => now()->toISOString(),
                'message' => 'RabbitMQ initialized successfully'
            ]), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);

            $channel->basic_publish($message, $exchange, $queue);

            $this->info('✅ RabbitMQ initialized successfully!');

            // Закрываем соединение
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
