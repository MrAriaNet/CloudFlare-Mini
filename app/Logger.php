<?php

declare(strict_types=1);

final class Logger
{
    private JsonStore $store;
    private const MAX_LOGS = 5000;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
    }

    public function log(
        string $action,
        ?array $actor = null,
        array $context = []
    ): void {
        $entry = [
            'id' => uuid(),
            'time' => now_iso(),
            'action' => $action,
            'actor_id' => $actor['id'] ?? null,
            'actor_username' => $actor['username'] ?? 'system',
            'ip' => client_ip(),
            'context' => $context,
        ];

        $this->store->update('logs', function (array $logs) use ($entry): array {
            array_unshift($logs, $entry);
            if (count($logs) > self::MAX_LOGS) {
                $logs = array_slice($logs, 0, self::MAX_LOGS);
            }
            return $logs;
        }, []);
    }

    public function all(): array
    {
        return $this->store->read('logs', []);
    }
}
