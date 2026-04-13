# in2shortcutcache – Correct cache lifetime for shortcut content elements in TYPO3

## Introduction

This TYPO3 extension fixes a known core issue (Forge [#91561](https://forge.typo3.org/issues/91561),
[#93661](https://forge.typo3.org/issues/93661)) where the page cache lifetime is not correctly calculated
when **"Insert Records"** (`CType = shortcut`) content elements reference time-controlled target elements.

### The Problem

When a shortcut element references a target element with a `starttime` or `endtime`, TYPO3 calculates the
cache lifetime only based on the shortcut record itself. Since the shortcut has no time settings, the
default lifetime of **86400 seconds (24 hours)** is used — instead of the remaining time until the next
visibility change of the referenced target element.

This affects TYPO3's page cache in general: pages are served from cache even after a scheduled visibility
change of a referenced element should have taken effect. Any caching layer built on top — such as
staticfilecache — inherits the same incorrect lifetime and compounds the problem further.

### The Solution

The extension listens to TYPO3's `ModifyCacheLifetimeForPageEvent` PSR-14 event. For each page render,
it:

1. Queries all `CType = shortcut` content elements on the current page
2. Parses their `records` field to find the referenced `tt_content` UIDs
3. Reads the `starttime` and `endtime` of those referenced records
4. Reduces the page cache lifetime to match the next scheduled visibility change

## Installation

```bash
composer req in2code/in2shortcutcache
```

No further configuration is required. The extension registers its event listener automatically via
`Configuration/Services.yaml`.

## Supported formats for the `records` field

The `tt_content.records` field (used by `CType = shortcut`) can contain UIDs in different formats.
All of the following are handled correctly:

| Format                  | Example                         | Handling                                       |
|-------------------------|---------------------------------|------------------------------------------------|
| Plain integer UIDs      | `123,456`                       | Resolved as `tt_content` UIDs                  |
| Table-prefixed UIDs     | `tt_content_123,tt_content_456` | Prefix stripped, resolved as `tt_content` UIDs |
| Mixed with other tables | `pages_123,tt_content_234`      | Non-`tt_content` entries are ignored           |

## Scope

The following fields of the referenced `tt_content` records are considered:

| Source        | Fields                                                                      | Condition                                                                      |
|---------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| TYPO3 core    | `starttime`, `endtime` (Unix timestamps)                                    | Always                                                                         |
| in2frequently | `tx_in2frequently_starttime`, `tx_in2frequently_endtime` (cron expressions) | Only if `in2code/in2frequently` is installed and `tx_in2frequently_active = 1` |

Support for `in2frequently` is optional. If the package is not installed, the behaviour is identical
to before. No configuration is required — the integration is activated automatically once
`in2code/in2frequently` is present.

The fix applies to direct shortcut references. Nested shortcuts (a shortcut referencing another shortcut
that in turn references a time-controlled element) are currently not resolved transitively.

## Configuration

The extension can be configured via the TYPO3 Extension Manager or `LocalConfiguration.php` under the
key `in2shortcutcache`.

| Option                        | Type | Default | Description                                                                                                                                                                                                                                                                                   |
|-------------------------------|------|---------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `enableDataHandlerCacheFlush` | bool | `true`  | When enabled, the page cache for all pages containing shortcut elements that reference a just-saved or deleted `tt_content` record is flushed immediately via the DataHandler hook. Disable this on large installations if the flush causes unwanted performance impact during backend saves. |

## Changelog

| Version | Date       | State   | Description                                                                                 |
|---------|------------|---------|---------------------------------------------------------------------------------------------|
| 1.0.0   | 2026-04-13 | Task    | Initial release                                                                             |
