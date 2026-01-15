# Website Support Speed Optimizer

Performance optimization plugin for WordPress targeting Core Web Vitals: TTFB, FCP, LCP, and CLS.

[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)

## Overview

Website Support Speed Optimizer is a lightweight performance plugin designed to address common PageSpeed Insights issues found on WordPress sites, particularly those using the Divi theme.

## Features

| Module | Description |
|--------|-------------|
| **Page Cache** | Full HTML caching reduces Time to First Byte (TTFB) from seconds to milliseconds |
| **Script Optimizer** | Defer and delay JavaScript execution to improve First Contentful Paint (FCP) |
| **Style Optimizer** | Inline critical CSS and defer non-critical stylesheets |
| **Image Optimizer** | Native lazy loading with LCP image prioritization |
| **Resource Hints** | Preconnect to third-party domains (TypeKit, CDNs) |
| **HTML Minifier** | Strip whitespace and comments from HTML output |
| **Font Optimizer** | Injects font-display: swap to prevent layout shifts |
| **CLS Optimizer** | Reserves space for dynamic elements to prevent layout shifts |
| **Instant Navigation** | Chrome Speculation Rules API prerenders pages on hover |

## Targeted Metrics

- **TTFB** - Time to First Byte
- **FCP** - First Contentful Paint
- **LCP** - Largest Contentful Paint
- **CLS** - Cumulative Layout Shift
- **TBT** - Total Blocking Time

## Installation

1. Download or clone this repository
2. Upload the `website-support-speed-optimizer` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Configure at **Settings > Speed Optimizer**

## Configuration

### Page Cache

Caches the full HTML output of pages for logged-out visitors. Automatically excludes:
- Admin pages
- Cart and checkout pages
- Logged-in users

### Script Optimizer

| Option | Description |
|--------|-------------|
| Defer JS | Adds `defer` attribute to scripts |
| Delay JS | Delays non-critical scripts until user interaction (click, touch, keydown) |

### Style Optimizer

- Inlines critical CSS in the document head
- Defers non-critical CSS using `media="print"` technique
- Includes optimized critical CSS for Divi themes

### Image Optimizer

- Adds `loading="lazy"` to below-fold images
- Adds `fetchpriority="high"` to LCP images
- Adds `decoding="async"` for non-blocking decode
- Automatically adds `aspect-ratio` to prevent CLS

### Resource Hints

Adds preconnect and dns-prefetch hints for external domains:
- TypeKit fonts
- Cloudflare CDN
- Custom URLs (configurable)

### Instant Navigation (Speculation Rules)

Uses Chrome's Speculation Rules API to prerender internal pages before the user clicks.

| Eagerness Level | Trigger | Use Case |
|-----------------|---------|----------|
| Conservative | mousedown/touchstart | Safest option |
| Moderate | hover | Recommended for most sites |
| Eager | Shortly after page load | High-traffic pages |
| Immediate | On page load | Landing pages with clear CTAs |

Automatically excludes:
- `/wp-admin/*`
- `/cart/*`, `/checkout/*`, `/my-account/*`
- `/login/*`, `/logout/*`
- External links
- Links with `.no-prerender` class

## Requirements

- WordPress 5.8+
- PHP 7.4+

## FAQ

### Is this compatible with other caching plugins?

It's recommended to disable other page caching plugins when using this plugin's page cache feature. You can use this plugin alongside CDN plugins like Cloudflare.

### Will this break my Divi theme?

The plugin has been specifically tested with Divi. The script delay feature is designed to safely delay Divi's scripts until user interaction without breaking functionality.

### How do I clear the cache?

Go to **Settings > Speed Optimizer** and click the "Clear Cache" button.

### Which browsers support Speculation Rules?

Chrome 121+ has full support. Other browsers gracefully ignore the speculation rules script tag.

## Changelog

### 1.0.4
- Fixed FCP/LCP regression caused by output buffering in Font Optimizer
- Simplified font-display injection using CSS-only approach

### 1.0.3
- Added Speculation Rules module for instant page navigation
- Prerenders internal links on hover using Chrome's Speculation Rules API
- Configurable eagerness levels (conservative, moderate, eager, immediate)
- Automatically excludes cart, checkout, login, and admin URLs

### 1.0.2
- Renamed plugin to Website Support Speed Optimizer
- Enhanced font-display: swap injection for all @font-face rules
- Improved CLS prevention for Divi sections

### 1.0.1
- Added CLS Optimizer module
- Added Font Optimizer module
- Fixed slow scrolling issue (removed scroll event listener)
- Enhanced critical CSS for Divi themes

### 1.0.0
- Initial release
- Page caching with TTL support
- JavaScript defer and delay
- CSS critical path optimization
- Image lazy loading with LCP priority
- Resource hints (preconnect, dns-prefetch)
- HTML minification

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.
