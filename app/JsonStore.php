<?php

declare(strict_types=1);

final class JsonStore
{
    private string $dataPath;

    public function __construct(?string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? (string) config('data_path');
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }

    public function path(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_\-]/i', '', $name) ?? 'store';
        return $this->dataPath . DIRECTORY_SEPARATOR . $name . '.json';
    }

    public function read(string $name, $default = [])
    {
        $file = $this->path($name);
        if (!is_file($file)) {
            return $default;
        }

        $fp = fopen($file, 'rb');
        if ($fp === false) {
            return $default;
        }

        try {
            flock($fp, LOCK_SH);
            $raw = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        if ($raw === false || trim($raw) === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public function write(string $name, $data): void
    {
        $file = $this->path($name);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode JSON for ' . $name);
        }

        $fp = fopen($file, 'c+b');
        if ($fp === false) {
            throw new RuntimeException('Unable to open store file: ' . $name);
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Unable to lock store file: ' . $name);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json . "\n");
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    public function update(string $name, callable $callback, $default = [])
    {
        $file = $this->path($name);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($file, 'c+b');
        if ($fp === false) {
            throw new RuntimeException('Unable to open store file: ' . $name);
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Unable to lock store file: ' . $name);
            }

            $raw = stream_get_contents($fp);
            $current = $default;
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $current = $decoded;
                }
            }

            $updated = $callback($current);
            $json = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('Failed to encode JSON for ' . $name);
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json . "\n");
            fflush($fp);
            flock($fp, LOCK_UN);
            return $updated;
        } finally {
            fclose($fp);
        }
    }
}
