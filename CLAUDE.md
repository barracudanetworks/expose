# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Expose is a PHP Intrusion Detection System (IDS) library, a maintained fork of PHPIDS. It scans input data for potential security threats (XSS, SQL injection, etc.) using regex-based filter rules and anti-evasion converters. Requires PHP >=8.1.

## Common Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run a specific test file
./vendor/bin/phpunit tests/ManagerTest.php

# Run a single test method
./vendor/bin/phpunit --filter testRunSuccess

# Run the named test suite
./vendor/bin/phpunit --testsuite expose
```

There are no lint or static analysis tools configured in this project.

## Architecture

The library lives under `src/Expose/` with PSR-4 autoloading (`Expose\` namespace).

**Core flow**: `Manager` is the main entry point. It takes input data, runs it through `Converter` classes (to normalize evasion techniques), then matches against `Filter` rules loaded into a `FilterCollection`. Matches produce `Report` objects with impact scores. If impact exceeds a configured threshold, the input is flagged.

**Key classes**:

- **Manager** — Orchestrator that runs filters against data. Implements PSR-3 `LoggerAwareInterface` and PSR-14 `EventDispatcherInterface`. Supports PSR-16 cache for results. Handles impact thresholds, field restrictions/exceptions.
- **Filter** — Single detection rule with a regex pattern, impact score, tags, and description.
- **FilterCollection** — Loads and manages filters from `filter_rules.json`. Implements `ArrayAccess`, `Iterator`, `Countable`.
- **Report** — Result for a matched variable: contains the variable name/value/path and matched filters.
- **FilterEvent** — PSR-14 event dispatched when filters match, carrying matched filters and total impact.

**Converters** (`Expose\Converter\`) — Anti-evasion normalization applied before pattern matching:
- `ConvertMisc` — URL encoding, HTML entities, base64, UTF-7, whitespace, etc.
- `ConvertJS` — JavaScript character codes, Unicode escapes, regex modifiers
- `ConvertSQL` — SQL hex encoding, keyword normalization, comment stripping

**Exporters** (`Expose\Export\`) — Format results from Manager:
- `Text` — Human-readable text output
- `Loopback` — Returns raw Report objects

## Testing

PHPUnit 9.x configured via `phpunit.xml.dist`. Tests are in `tests/` and mirror the source structure. `MockListener.php` provides a test utility for event dispatch verification. CI runs tests across PHP 8.1, 8.2, 8.3, and 8.4 via GitHub Actions (`.github/workflows/build.yml`).

## PSR Interfaces

The library integrates with three PSR standards — logging (PSR-3), caching (PSR-16), and event dispatching (PSR-14). These are optional: Manager works without a logger, cache, or event listeners configured.
