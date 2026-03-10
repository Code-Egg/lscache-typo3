<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Service;

final class HeaderBuilder
{
    public function buildCacheControl(string $mode, int $maxAge, bool $enableEsi = false): string
    {
        $mode = $mode === 'private' ? 'private' : 'public';
        $parts = [$mode, 'max-age=' . max(0, $maxAge)];
        if ($enableEsi) {
            $parts[] = 'esi=on';
        }
        return implode(',', $parts);
    }

    public function buildVaryHeader(array $cookies): ?string
    {
        if ($cookies === []) {
            return null;
        }

        $parts = [];
        foreach ($cookies as $cookie) {
            $cookie = trim($cookie);
            if ($cookie === '') {
                continue;
            }
            $parts[] = 'cookie=' . $cookie;
        }

        if ($parts === []) {
            return null;
        }

        return implode(',', $parts);
    }

    public function normalizeTags(array $tags, int $maxTags, int $maxHeaderLength): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = $this->sanitizeTag((string)$tag);
            if ($tag === '') {
                continue;
            }
            $clean[$tag] = true;
            if ($maxTags > 0 && count($clean) >= $maxTags) {
                break;
            }
        }

        if ($clean === []) {
            return [];
        }

        $result = array_keys($clean);
        if ($maxHeaderLength > 0) {
            $trimmed = [];
            $length = 0;
            foreach ($result as $value) {
                $segmentLength = strlen($value);
                if ($trimmed !== []) {
                    $segmentLength += 1;
                }
                if ($length + $segmentLength > $maxHeaderLength) {
                    break;
                }
                $trimmed[] = $value;
                $length += $segmentLength;
            }
            $result = $trimmed;
        }

        return $result;
    }

    public function sanitizeTag(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        $tag = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tag);
        $tag = trim((string)$tag, '_');
        return $tag;
    }
}
