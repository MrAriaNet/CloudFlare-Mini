<?php

declare(strict_types=1);

final class Logger
{
    private JsonStore $store;
    private const MAX_LOGS = 5000;
    private const MAX_CRON_LOGS = 2000;
    private const MAX_DNS_LOGS = 5000;
    private const MAX_PTR_LOGS = 5000;

    public const CHANNEL_AUDIT = 'logs';
    public const CHANNEL_CRON = 'cron_logs';
    public const CHANNEL_DNS = 'dns_logs';
    public const CHANNEL_PTR = 'ptr_logs';

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
        $this->migrateSplitLogs();
    }

    public function log(
        string $action,
        ?array $actor = null,
        array $context = []
    ): void {
        $this->write(self::CHANNEL_AUDIT, $action, $actor, $context, self::MAX_LOGS);
    }

    public function cron(
        string $action,
        ?array $actor = null,
        array $context = []
    ): void {
        if ($actor === null) {
            $actor = ['username' => 'cron'];
        }
        // PTR cron actions belong in PTR channel only
        if ($this->isPtrAction($action, $context)) {
            $this->write(self::CHANNEL_PTR, $action, $actor, $context, self::MAX_PTR_LOGS);
            return;
        }
        $this->write(self::CHANNEL_CRON, $action, $actor, $context, self::MAX_CRON_LOGS);
    }

    public function dns(
        string $action,
        ?array $actor = null,
        array $context = []
    ): void {
        if ($this->isPtrAction($action, $context)) {
            $this->write(self::CHANNEL_PTR, $action, $actor, $context, self::MAX_PTR_LOGS);
            return;
        }
        $this->write(self::CHANNEL_DNS, $action, $actor, $context, self::MAX_DNS_LOGS);
    }

    public function ptr(
        string $action,
        ?array $actor = null,
        array $context = []
    ): void {
        if ($actor === null) {
            $actor = ['username' => 'ptr'];
        }
        $this->write(self::CHANNEL_PTR, $action, $actor, $context, self::MAX_PTR_LOGS);
    }

    public function all(): array
    {
        return $this->store->read(self::CHANNEL_AUDIT, []);
    }

    public function allCron(): array
    {
        return $this->store->read(self::CHANNEL_CRON, []);
    }

    public function allDns(): array
    {
        return $this->store->read(self::CHANNEL_DNS, []);
    }

    public function allPtr(): array
    {
        return $this->store->read(self::CHANNEL_PTR, []);
    }

    public function clear(string $channel): int
    {
        $allowed = [
            self::CHANNEL_AUDIT,
            self::CHANNEL_CRON,
            self::CHANNEL_DNS,
            self::CHANNEL_PTR,
        ];
        if (!in_array($channel, $allowed, true)) {
            throw new InvalidArgumentException('Invalid log channel.');
        }
        $existing = $this->store->read($channel, []);
        $count = is_array($existing) ? count($existing) : 0;
        $this->store->write($channel, []);
        return $count;
    }

    public function clearAudit(): int
    {
        return $this->clear(self::CHANNEL_AUDIT);
    }

    public function clearDns(): int
    {
        return $this->clear(self::CHANNEL_DNS);
    }

    public function clearCron(): int
    {
        return $this->clear(self::CHANNEL_CRON);
    }

    public function clearPtr(): int
    {
        return $this->clear(self::CHANNEL_PTR);
    }

    public function isPtrAction(string $action, array $context = []): bool
    {
        if (strpos($action, 'ptr.') === 0) {
            return true;
        }
        $reason = (string) ($context['reason'] ?? '');
        if ($reason !== '' && strpos($reason, 'ptr_') === 0) {
            return true;
        }
        return false;
    }

    private function write(
        string $channel,
        string $action,
        ?array $actor,
        array $context,
        int $max
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

        $this->store->update($channel, function (array $logs) use ($entry, $max): array {
            array_unshift($logs, $entry);
            if (count($logs) > $max) {
                $logs = array_slice($logs, 0, $max);
            }
            return $logs;
        }, []);
    }

    /**
     * Move legacy rows into the correct channel (audit / cron / dns / ptr).
     */
    private function migrateSplitLogs(): void
    {
        $this->migrateChannel(self::CHANNEL_AUDIT);
        $this->migrateChannel(self::CHANNEL_CRON);
        $this->migrateChannel(self::CHANNEL_DNS);
    }

    private function migrateChannel(string $channel): void
    {
        $rows = $this->store->read($channel, []);
        if (!is_array($rows) || $rows === []) {
            return;
        }

        $keep = [];
        $cronMoved = [];
        $dnsMoved = [];
        $ptrMoved = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $action = (string) ($row['action'] ?? '');
            $context = is_array($row['context'] ?? null) ? $row['context'] : [];

            if ($this->isPtrAction($action, $context)) {
                $ptrMoved[] = $row;
                continue;
            }

            if ($channel === self::CHANNEL_AUDIT) {
                if ($action === 'sync.cron' || $action === 'sync.http_cron' || $action === 'sync.cli') {
                    $cronMoved[] = $row;
                } elseif ($action === 'dns.create' || $action === 'dns.update' || $action === 'dns.delete') {
                    $dnsMoved[] = $row;
                } else {
                    $keep[] = $row;
                }
                continue;
            }

            // For cron/dns channels: only peel PTR rows; keep the rest
            $keep[] = $row;
        }

        if ($cronMoved === [] && $dnsMoved === [] && $ptrMoved === []) {
            return;
        }

        // Only rewrite source channel if something moved out
        if ($ptrMoved !== [] || ($channel === self::CHANNEL_AUDIT && ($cronMoved !== [] || $dnsMoved !== []))) {
            $this->store->write($channel, $keep);
        }

        if ($cronMoved !== []) {
            $this->mergeIntoChannel(self::CHANNEL_CRON, $cronMoved, self::MAX_CRON_LOGS);
        }
        if ($dnsMoved !== []) {
            $this->mergeIntoChannel(self::CHANNEL_DNS, $dnsMoved, self::MAX_DNS_LOGS);
        }
        if ($ptrMoved !== []) {
            $this->mergeIntoChannel(self::CHANNEL_PTR, $ptrMoved, self::MAX_PTR_LOGS);
        }
    }

    private function mergeIntoChannel(string $channel, array $moved, int $max): void
    {
        $this->store->update($channel, function (array $existing) use ($moved, $max): array {
            $merged = array_merge($moved, $existing);
            // Dedupe by id when present
            $byId = [];
            foreach ($merged as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id === '') {
                    $byId[uuid()] = $row;
                    continue;
                }
                $byId[$id] = $row;
            }
            $merged = array_values($byId);
            usort($merged, static function (array $a, array $b): int {
                return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
            });
            if (count($merged) > $max) {
                $merged = array_slice($merged, 0, $max);
            }
            return $merged;
        }, []);
    }
}
