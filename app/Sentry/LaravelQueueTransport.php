<?php

namespace App\Sentry;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use App\Jobs\SentryJob;

class LaravelQueueTransport implements TransportInterface
{
    /**
     * Sends the given event to the Sentry server.
     *
     * @param Event $event The event to send
     */
    public function send(Event $event): Result
    {
        // Serialize the event to an array for queue safety
        // This is a simplified version of what Sentry does internally
        $data = [
            'event_id' => (string) $event->getId(),
            'timestamp' => $event->getTimestamp(),
            'level' => $event->getLevel() ? (string) $event->getLevel() : null,
            'logger' => $event->getLogger(),
            'transaction' => $event->getTransaction(),
            'server_name' => $event->getServerName(),
            'release' => $event->getRelease(),
            'environment' => $event->getEnvironment(),
            'message' => $event->getMessage(),
            'tags' => $event->getTags(),
            'extra' => $event->getExtra(),
            'user' => $event->getUser() ? [
                'id' => $event->getUser()->getId(),
                'email' => $event->getUser()->getEmail(),
                'ip_address' => $event->getUser()->getIpAddress(),
                'username' => $event->getUser()->getUsername(),
                'metadata' => $event->getUser()->getMetadata(),
            ] : null,
            // Add more fields as needed, or use a more robust serializer
        ];

        dispatch(new SentryJob($data));

        return new Result(ResultStatus::success());
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
