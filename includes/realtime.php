<?php
// includes/realtime.php — order/status change notifier (file version counter)

function realtime_state_file(): string
{
    return dirname(__DIR__) . '/sessions/realtime_state.json';
}

function realtime_bump(): int
{
    $path = realtime_state_file();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $version = 0;
    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return 0;
    }

    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $state = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
        if (!is_array($state)) {
            $state = [];
        }

        $version = (int) ($state['version'] ?? 0) + 1;
        $payload = [
            'version' => $version,
            'at' => microtime(true),
        ];

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($payload));
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $version;
}

function realtime_read_state(): ?array
{
    $path = realtime_state_file();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $state = json_decode($raw, true);
    return is_array($state) ? $state : null;
}

function realtime_notify_orders(): void
{
    realtime_bump();
}
