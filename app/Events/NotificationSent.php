<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(Notifikasi $notification)
    {
        $this->notification = $notification;
        
        \Log::info('📨 NotificationSent event created', [
            'notification_id' => $notification->id_notifikasi,
            'user_id' => $notification->id_user,
            'type' => $notification->tipe_notifikasi,
            'message' => $notification->isi_notifikasi
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channelName = 'notifications.' . $this->notification->id_user;
        
        \Log::info('📡 Broadcasting notification', [
            'channel' => $channelName,
            'notification_id' => $this->notification->id_notifikasi
        ]);
        
        return [
            new PrivateChannel($channelName)
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id_notifikasi' => $this->notification->id_notifikasi,
            'tipe_notifikasi' => $this->notification->tipe_notifikasi,
            'isi_notifikasi' => $this->notification->isi_notifikasi,
            'data' => $this->notification->data,
            'action_url' => $this->notification->action_url,
            'is_read' => $this->notification->is_read,
            'created_at' => $this->notification->created_at->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NotificationReceived';
    }
}
