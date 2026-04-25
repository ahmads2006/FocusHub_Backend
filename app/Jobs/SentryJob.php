<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sentry\SentrySdk;

class SentryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        // Re-construct the event from the array data
        $event = \Sentry\Event::createEvent();
        if (isset($this->data['event_id'])) {
            // EventId is private, we can't easily set it back without reflection or using the factory
            // But for sending, the ID will be regenerated or we can ignore it
        }
        
        $event->setTimestamp($this->data['timestamp'] ?? microtime(true));
        $event->setLevel(isset($this->data['level']) ? \Sentry\Severity::{$this->data['level']}() : \Sentry\Severity::error());
        $event->setLogger($this->data['logger'] ?? null);
        $event->setTransaction($this->data['transaction'] ?? null);
        $event->setServerName($this->data['server_name'] ?? null);
        $event->setRelease($this->data['release'] ?? null);
        $event->setEnvironment($this->data['environment'] ?? null);
        
        if (isset($this->data['message'])) {
            $event->setMessage($this->data['message']);
        }
        
        $event->setTags($this->data['tags'] ?? []);
        $event->setExtra($this->data['extra'] ?? []);
        
        if (isset($this->data['user'])) {
            $userData = new \Sentry\UserDataBag();
            $userData->setId($this->data['user']['id'] ?? null);
            $userData->setEmail($this->data['user']['email'] ?? null);
            $userData->setIpAddress($this->data['user']['ip_address'] ?? null);
            $userData->setUsername($this->data['user']['username'] ?? null);
            
            $metadata = $this->data['user']['metadata'] ?? [];
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $userData->setMetadata((string)$key, $value);
                }
            }
            
            $event->setUser($userData);
        }

        // Send the event via the Sentry Hub
        \Sentry\SentrySdk::getCurrentHub()->captureEvent($event);
    }
}
