<?php

declare(strict_types=1);

/**
 * Dedicated PTR audit / auto-delete cron (separate lock from sync).
 */
final class PtrCronRunner
{
    private PtrService $ptr;
    private Logger $logger;

    public function __construct(PtrService $ptr, Logger $logger)
    {
        $this->ptr = $ptr;
        $this->logger = $logger;
    }

    /**
     * @return array{lines:array,result:?array,locked:bool,skipped_interval:bool}
     */
    public function run(bool $force = false, string $source = 'cli'): array
    {
        $lockTtl = (int) config('cron_lock_ttl', 900);
        $lock = new CronLock('ptr_cron');
        if (!$lock->acquire($lockTtl)) {
            return [
                'lines' => ['skipped: PTR cron already running'],
                'result' => null,
                'locked' => true,
                'skipped_interval' => false,
            ];
        }

        try {
            @set_time_limit((int) config('cron_time_limit', 600));
            $settings = $this->ptr->getSettings();

            if (!$force && empty($settings['auto_check'])) {
                return [
                    'lines' => ['skipped: auto_check is disabled in PTR settings'],
                    'result' => null,
                    'locked' => false,
                    'skipped_interval' => true,
                ];
            }

            if (!$force && !$this->ptr->shouldAutoRun()) {
                return [
                    'lines' => ['skipped: PTR audit interval not reached (last run still fresh)'],
                    'result' => null,
                    'locked' => false,
                    'skipped_interval' => true,
                ];
            }

            $result = $this->ptr->runAudit(null, ['username' => 'ptr_cron'], [
                'fast' => true,
                'limit' => (int) config('ptr_audit_batch', 300),
                'dedupe' => true,
            ]);

            $line = 'ptr_audit: checked=' . $result['checked']
                . ' ok=' . $result['ok']
                . ' mismatched=' . $result['mismatched']
                . ' deleted=' . $result['deleted']
                . ' open=' . $result['open_issues'];

            $this->logger->ptr($source === 'http' ? 'ptr.audit.http_cron' : 'ptr.audit.cron', ['username' => 'ptr_cron'], [
                'checked' => $result['checked'],
                'ok' => $result['ok'],
                'mismatched' => $result['mismatched'],
                'deleted' => $result['deleted'],
                'open_issues' => $result['open_issues'],
                'fast' => true,
                'force' => $force,
            ]);

            return [
                'lines' => [$line],
                'result' => $result,
                'locked' => false,
                'skipped_interval' => false,
            ];
        } finally {
            $lock->release();
        }
    }
}
