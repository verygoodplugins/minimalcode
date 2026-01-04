# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MinimalCode is a WordPress theme for technical blogs. Key features: dark mode with system preference detection, Prism.js code highlighting, reading progress bar, and copy-to-clipboard for code blocks.

**Environment:** Local WP development site at `drunksupport.local`

## Development Commands

```bash
# Check PHP syntax
php -l functions.php

# Validate all PHP files
find . -name "*.php" -exec php -l {} \;

# Access the site
open http://drunksupport.local
```

No build process - pure PHP/CSS/JS with external CDN dependencies (Prism.js, Google Fonts).

## Architecture

### Template Hierarchy

Standard WordPress template structure:
- `index.php` - Main posts loop
- `single.php` - Individual post display
- `archive.php` - Category/tag archives
- `404.php` - Error page with search and recent posts
- `header.php` / `footer.php` - Site chrome with dark mode toggle

### Styling Architecture

Three-layer CSS system:
1. **style.css** - CSS variables, base typography, code blocks, theme metadata
2. **assets/css/custom.css** - Layout, components (header, footer, navigation, comments, post cards)
3. **assets/css/editor-style.css** - Gutenberg editor styles

CSS variables in `:root` and `[data-theme="dark"]` control theming. All colors reference variables like `--bg-primary`, `--text-primary`, `--accent`.

### JavaScript Modules (assets/js/theme.js)

Four independent modules in an IIFE:
- **ThemeManager** - Dark mode toggle, localStorage persistence, system preference detection
- **SmoothScroll** - Anchor link smooth scrolling
- **CodeCopyButton** - Adds copy buttons to `<pre>` blocks
- **ReadingProgress** - Progress bar on single posts (`.single-post` class required)

### Key Functions (functions.php)

- `minimalcode_reading_time()` - Calculates read time (200 wpm)
- `minimalcode_social_links()` - Returns array from Customizer settings
- `minimalcode_scripts()` - Enqueues Prism.js with language components

### External Dependencies (CDN)

- Prism.js 1.29.0 (tomorrow theme) - Code highlighting
- Google Fonts - Inter (body), JetBrains Mono (code)

Supported languages: JavaScript, Python, Bash, JSON, CSS, PHP, TypeScript. Add languages in `minimalcode_scripts()`.

## Theme Customizer Settings

Social links stored as `minimalcode_twitter`, `minimalcode_github`, `minimalcode_linkedin`, `minimalcode_email`.

## CSS Variable Reference

Key variables for color modifications:
```css
--bg-primary, --bg-secondary, --bg-code
--text-primary, --text-secondary, --text-tertiary
--accent, --accent-hover
--border-color
```

## WordPress Requirements

- PHP 8.0+
- WordPress 6.0+
- Registered nav menus: `primary`, `social`
