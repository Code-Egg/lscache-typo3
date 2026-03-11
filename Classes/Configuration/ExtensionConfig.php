<?php

declare(strict_types=1);

namespace LiteSpeed\Lscache\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;

final class ExtensionConfig
{
    private array $config;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration)
    {
        $this->config = $this->load();
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    public function getCacheControl(): string
    {
        return $this->config['cacheControl'];
    }

    public function getDefaultMaxAge(): int
    {
        return $this->config['defaultMaxAge'];
    }

    public function getLoggedInBehavior(): string
    {
        return $this->config['loggedInBehavior'];
    }

    public function tagPrivateResponses(): bool
    {
        return $this->config['tagPrivateResponses'];
    }

    public function addPageIdTag(): bool
    {
        return $this->config['addPageIdTag'];
    }

    public function getTagPrefix(): string
    {
        return $this->config['tagPrefix'];
    }

    public function getExtraTags(): array
    {
        return $this->config['extraTags'];
    }

    public function getMaxTags(): int
    {
        return $this->config['maxTags'];
    }

    public function getMaxHeaderLength(): int
    {
        return $this->config['maxHeaderLength'];
    }

    public function getVaryCookies(): array
    {
        return $this->config['varyCookies'];
    }

    private function load(): array
    {
        $defaults = [
            'enabled' => true,
            'cacheControl' => 'public',
            'defaultMaxAge' => 3600,
            'loggedInBehavior' => 'no-cache',
            'tagPrivateResponses' => false,
            'addPageIdTag' => true,
            'tagPrefix' => 't3',
            'extraTags' => [],
            'maxTags' => 100,
            'maxHeaderLength' => 4096,
            'varyCookies' => [],
        ];

        $raw = [];
        try {
            $raw = $this->extensionConfiguration->get('lscache');
        } catch (ExtensionConfigurationExtensionNotConfiguredException) {
            $raw = [];
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        $config = array_merge($defaults, $raw);

        $config['enabled'] = (bool)$config['enabled'];
        $config['cacheControl'] = strtolower(trim((string)$config['cacheControl'])) ?: 'public';
        if (!in_array($config['cacheControl'], ['public', 'private'], true)) {
            $config['cacheControl'] = 'public';
        }
        $config['defaultMaxAge'] = max(0, (int)$config['defaultMaxAge']);
        $config['loggedInBehavior'] = strtolower(trim((string)$config['loggedInBehavior'])) ?: 'no-cache';
        if (!in_array($config['loggedInBehavior'], ['no-cache', 'private'], true)) {
            $config['loggedInBehavior'] = 'no-cache';
        }
        $config['tagPrivateResponses'] = (bool)$config['tagPrivateResponses'];
        $config['addPageIdTag'] = (bool)$config['addPageIdTag'];
        $config['tagPrefix'] = $this->sanitizeToken((string)$config['tagPrefix'], 't3');
        $config['extraTags'] = $this->splitList((string)($config['extraTags'] ?? ''));
        $config['maxTags'] = max(0, (int)$config['maxTags']);
        $config['maxHeaderLength'] = max(128, (int)$config['maxHeaderLength']);
        $config['varyCookies'] = $this->splitList((string)($config['varyCookies'] ?? ''));

        return $config;
    }

    private function splitList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static fn(string $item): bool => $item !== '');
        return array_values(array_unique($parts));
    }

    private function sanitizeToken(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value);
        $value = trim($value ?? '', '-');
        return $value !== '' ? $value : $fallback;
    }
}
