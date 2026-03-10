# lscache-typo3

LiteSpeed Cache integration for TYPO3.

This extension emits LiteSpeed Cache response headers for frontend requests and provides a secure purge endpoint used to clear LSCache when TYPO3 caches are flushed.

## Features

- Adds `X-LiteSpeed-Cache-Control` based on TYPO3 cacheability.
- Adds `X-LiteSpeed-Tag` derived from TYPO3 cache tags (plus page and site tags).
- Adds `X-LiteSpeed-Vary` for cookie-based variations.
- Purges LSCache on TYPO3 cache flush events.
- CLI command to purge LSCache manually.

## Requirements

- TYPO3 12.4 LTS or 13.4 LTS
- PHP 8.1+
- OpenLiteSpeed or LiteSpeed Web Server with LSCache enabled

## Installation

Add the extension to your project and activate it in the TYPO3 Extension Manager.

## Configuration

Open the Install Tool and configure the extension settings.

Key settings:

- `purgeToken`: required for purge requests.
- `purgePath`: default `/_lscache/purge`.
- `purgeOnCacheFlush`: enable automatic purge on cache flush.
- `varyCookies`: comma-separated list of cookies to vary on.

## Web Server

Ensure LSCache is enabled for the site. Example (virtual host or `.htaccess`):

```
CacheLookup public on
```

## Manual Purge

```
vendor/bin/typo3 lscache:purge
```

Limit to a site identifier:

```
vendor/bin/typo3 lscache:purge --site=my-site
```

## Security Notes

- Keep `purgeToken` secret.
- Consider restricting the purge endpoint by IP at the web server layer.
