<?php

declare(strict_types=1);

/**
 * Prevent overlapping cron processes.
 */
final class CronLock
{
    private string $path;
    /** @var resource|null */
    private $handle = null;

    public function __construct(string $name = 'cron')
    {
        $dir = (string) config('data_path');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $name) ?: 'cron';
        $this->path = $dir . DIRECTORY_SEPARATOR . $safe . '.lock';
    }

    /**
     * @param int $staleAfterSeconds Break lock if holder died and file is older than this
     */
    public function acquire(int $staleAfterSeconds = 900): bool
    {
        $fp = @fopen($this->path, 'c+b');
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            // Another process holds the lock
            $age = time() - (int) @filemtime($this->path);
            if ($age > $staleAfterSeconds) {
                // Stale lock from a dead process — try to steal
                flock($fp, LOCK_UN);
                fclose($fp);
                @unlink($this->path);
                $fp = @fopen($this->path, 'c+b');
                if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
                    if (is_resource($fp)) {
                        fclose($fp);
                    }
                    return false;
                }
            } else {
                fclose($fp);
                return false;
            }
        }

        ftruncate($fp, 0);
        fwrite($fp, (string) getmypid() . "\n" . date('c') . "\n");
        fflush($fp);
        $this->handle = $fp;
        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
