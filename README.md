# lscache-typo3

LiteSpeed Cache integration for TYPO3.

This extension emits LiteSpeed Cache response headers for frontend requests and automatically purges LSCache when TYPO3 caches are flushed.

## Features

- Adds `X-LiteSpeed-Cache-Control` based on TYPO3 cacheability.
- Adds `X-LiteSpeed-Tag` derived from TYPO3 cache tags (plus page and site tags).
- Adds `X-LiteSpeed-Vary` for cookie-based variations.
- Purges LSCache automatically on TYPO3 cache flush events.
- Purges LSCache for specific pages when TYPO3 clears a page cache.

## Requirements

- TYPO3 12.4+
- PHP 8.1+
- OpenLiteSpeed or LiteSpeed Web Server with LSCache enabled

## Installation

```
composer require cold-egg/lscache-typo3
```

Activate the extension in the TYPO3 Extension Manager.

## Configuration

Go to **Admin Panel → Settings → Extension Configuration → lscache**.

| Setting | Default | Description |
|---|---|---|
| `enabled` | `1` | Enable/disable all LSCache headers |
| `cacheControl` | `public` | Cache-Control mode for anonymous users (`public`\|`private`) |
| `defaultMaxAge` | `3600` | Fallback max-age in seconds when TYPO3 provides no lifetime |
| `loggedInBehavior` | `no-cache` | Behavior for logged-in frontend users (`no-cache`\|`private`) |
| `tagPrivateResponses` | `0` | Whether to add tags to private responses |
| `addPageIdTag` | `1` | Add a `t3_page_X` tag to every cached response |
| `tagPrefix` | `t3` | Prefix applied to all cache tags |
| `extraTags` | _(empty)_ | Additional tags to emit on every response (comma-separated) |
| `maxTags` | `100` | Maximum number of tags per response |
| `maxHeaderLength` | `4096` | Maximum byte length of the `X-LiteSpeed-Tag` header |
| `varyCookies` | _(empty)_ | Cookies to vary on (comma-separated) |

## Web Server

Ensure LSCache is enabled for the site. Example (virtual host or `.htaccess`):

```
CacheLookup public on
```
