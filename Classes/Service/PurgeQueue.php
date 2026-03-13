<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Service;

use TYPO3\CMS\Core\Core\Environment;

/**
 * File-based queue for pending LiteSpeed purge signals.
 *
 * Backend hooks and event listeners write to this queue (since LiteSpeed may not
 * process X-LiteSpeed-Purge headers sent on backend AJAX responses). The frontend
 * middleware consumes the queue and injects the purge header into the next
 * PHP-served frontend response, which LiteSpeed always processes.
 */
final class PurgeQueue
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = Environment::getVarPath() . '/lscache_purge_queue.json';
    }

    public function add(bool $purgeAll, array $tags = []): void
    {
        $fp = @fopen($this->filePath, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $data = json_decode((string)stream_get_contents($fp), true) ?? [];
            if ($purgeAll || ($data['purgeAll'] ?? false)) {
                $data = ['purgeAll' => true, 'tags' => []];
            } else {
                $data = [
                    'purgeAll' => false,
                    'tags' => array_values(array_unique(array_merge((array)($data['tags'] ?? []), $tags))),
                ];
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)json_encode($data));
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    public function consume(): ?string
    {
        if (!file_exists($this->filePath)) {
            return null;
        }
        $fp = @fopen($this->filePath, 'c+');
        if ($fp === false) {
            return null;
        }
        $result = null;
        try {
            if (!flock($fp, LOCK_EX)) {
                return null;
            }
            $data = json_decode((string)stream_get_contents($fp), true) ?? [];
            if ($data['purgeAll'] ?? false) {
                $result = '*';
            } elseif (!empty($data['tags'])) {
                $parts = array_map(static fn(string $t): string => 'tag=' . $t, (array)$data['tags']);
                $result = 'public,' . implode(',', $parts);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)json_encode(['purgeAll' => false, 'tags' => []]));
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
        return $result;
    }
}
