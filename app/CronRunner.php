<?php

declare(strict_types=1);

/**
 * Cloudflare account sync only (no PTR audit).
 */
final class CronRunner
{
    private SyncService $sync;
    private Logger $logger;
    private JsonStore $store;

    public function __construct(SyncService $sync, Logger $logger, JsonStore $store)
    {
        $this->sync = $sync;
        $this->logger = $logger;
        $this->store = $store;
    }

    /**
     * @return array{ok:int,failed:int,skipped:int,lines:array,locked:bool}
     */
    public function run(bool $force = false, string $source = 'cli'): array
    {
        $lockTtl = (int) config('cron_lock_ttl', 900);
        $lock = new CronLock('main_cron');
        if (!$lock->acquire($lockTtl)) {
            return [
                'ok' => 0,
                'failed' => 0,
                'skipped' => 0,
                'lines' => ['skipped: sync cron already running'],
                'locked' => true,
            ];
        }

        try {
            @set_time_limit((int) config('cron_time_limit', 600));
            $this->sync->useCronLogging(true);

            $accounts = $this->store->read('accounts', []);
            $ok = 0;
            $fail = 0;
            $skipped = 0;
            $lines = [];

            foreach ($accounts as $account) {
                $name = (string) ($account['name'] ?? $account['id'] ?? 'unknown');
                $accountId = (string) ($account['id'] ?? '');

                if (!$force && $accountId !== '' && !$this->sync->needsSync($accountId)) {
                    $lines[] = $name . ': skipped (fresh)';
                    $skipped++;
                    continue;
                }

                if ($this->sync->syncAccount($account)) {
                    $lines[] = $name . ': ok';
                    $ok++;
                } else {
                    $lines[] = $name . ': failed';
                    $fail++;
                }
            }

            $this->logger->cron($source === 'http' ? 'sync.http_cron' : 'sync.cron', ['username' => 'cron'], [
                'ok' => $ok,
                'failed' => $fail,
                'skipped' => $skipped,
                'force' => $force,
            ]);

            return [
                'ok' => $ok,
                'failed' => $fail,
                'skipped' => $skipped,
                'lines' => $lines,
                'locked' => false,
            ];
        } finally {
            $lock->release();
        }
    }
}
