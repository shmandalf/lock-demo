<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $taskId;

    public $status;

    public $attempt;

    public $data;

    public $timestamp;

    public function __construct($taskId, $status, $attempt = 1, $data = [])
    {
        $this->taskId = $taskId;
        $this->status = $status;
        $this->attempt = $attempt;
        $this->data = $data;
        $this->timestamp = now()->toDateTimeString();

        \Log::info('Event created', [
            'task_id' => $taskId,
            'status' => $status,
            'attempt' => $attempt,
            'driver' => config('broadcasting.default'),
        ]);
    }

    public function broadcastOn()
    {
        \Log::debug('Broadcasting on channel: tasks', [
            'task_id' => $this->taskId,
            'status' => $this->status,
        ]);

        return new Channel('tasks');
    }

    public function broadcastAs()
    {
        return 'task.status.changed';
    }

    public function broadcastWith()
    {
        return [
            'taskId' => $this->taskId,
            'status' => $this->status,
            'attempt' => $this->attempt,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }

    // Добавляем ретраи для broadcasting
    public function broadcastWhen()
    {
        // Всегда пытаемся отправить
        return true;
    }

    public function broadcastAfterCommit()
    {
        // Не ждем коммита, отправляем сразу
        return false;
    }
}
