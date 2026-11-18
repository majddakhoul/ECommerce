<?php

namespace Tests\Support;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PusherLikeBroadcaster extends Broadcaster
{
    public function auth($request)
    {
        $channel = (string) $request->channel_name;

        if (!str_starts_with($channel, 'private-')) {
            throw new AccessDeniedHttpException;
        }

        $name = substr($channel, strlen('private-'));

        if (!$this->retrieveUser($request, $name)) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $name);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return ['auth' => 'test-key:signature'];
    }

    public function broadcast(array $channels, $event, array $payload = []): void {}
}
