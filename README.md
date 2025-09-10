# SluInk

SluInk is a tiny toolchain to turn a folder of **Markdown files** into a **static website** using Pandoc.
It focuses on **clarity, light pages, and durability** (no heavy JS, print‑friendly, accessible).

---

## Features

- **Markdown → HTML** with [Pandoc](https://pandoc.org/) (HTML5 template).
- **3 themes**: light, dark, academic (ivory). User preference is stored locally.
- **Clean layout** (TOC, header/aside/footer) with accessible landmarks.
- **Internal links rewritten** from `.md` to `.html` via a small Lua filter.
- **Self‑contained output** in `out/` (pure static files; no server runtime needed).
- Optional prev/next metadata for linear reading.

---

## Requirements

Install:

- **Pandoc** (2.x or newer recommended; 3.x works great)  
- **PHP** (7.4+ works; 8.3+ recommended) — used to orchestrate the build

> No separate Lua installation is required: Pandoc embeds a Lua runtime for filters.

---

## Project layout

```
SluInk/
├─ assets/                 # source assets (CSS, JS, icons)
│  ├─ style/style.css
│  ├─ script/script.js
│  └─ img/{sun,moon,file}-regular-full.svg
├─ source/                 # your Markdown content (.md)
│  └─ index.md             # site root
├─ template/
│  ├─ SluInk.html5         # Pandoc template (HTML5 + placeholders)
│  └─ rewrite_md_links.lua # Lua filter that rewrites .md → .html in links
├─ out/                    # generated static site (created by the build)
└─ build.php               # build script (scans, copies, invokes Pandoc)
```

> Depending on the version of the build script: assets are copied to **`out/assets/`** (recommended).
> If your current script still copies to the root of `out/`, switch to the updated one that places
> them under `out/assets/` and updates the template metadata accordingly.

---

## Usage

1. Put your Markdown files under `source/` (the output will mirror your folder structure).
2. Run the build:

```bash
php build.php
# output goes to: out/
```

3. Preview locally (prevents `file://` issues with localStorage/theme preference):

```bash
php -S 127.0.0.1:8080 -t out
# open http://127.0.0.1:8080
```

---

## How it works (in short)

- The PHP script scans `source/**.md` and invokes **Pandoc** for each file using the template `template/SluInk.html5`.
- The script passes template **metadata** so that the template can reference shared assets correctly:
  - `css_href`, `js_href`, `sun_href`, `moon_href`, `file_href`, `home_href`, `footer`
  - plus, for inner pages: `prev_href`, `next_href`, `prev_title`, `next_title`
- A small **Lua filter** (`template/rewrite_md_links.lua`) rewrites internal links from `*.md` to `*.html`
  (anchors like `#section` are preserved).

The generated site is plain HTML+CSS (and a tiny optional JS for theme switching).

---

## Theming

SluInk ships three themes: **light**, **dark**, and **academic** (ivory/paper).
A tiny inline **boot snippet** in the template reads the user preference from `localStorage` and
applies the theme **before** loading CSS to avoid flashes.

Icons used in the theme switcher are passed as template metadata and copied to the output.

---

## Troubleshooting

- **Assets not found / icons missing**  
  Ensure the build script **creates** `out/assets/` _before_ copying files, and that it passes
  `css_href/js_href/sun_href/moon_href/file_href` pointing to `assets/...` (with a proper relative prefix for nested pages).

- **HTML content appears inside an HTML comment**  
  Pandoc expands placeholders even inside comments. Remove the big header comment in the template
  or escape placeholders in comments (example: write `$$body$$` instead of `$body$`).

- **LocalStorage theme not sticking in local previews**  
  Serve via `php -S 127.0.0.1:8080 -t out` instead of opening files with `file://`.

---

## License

This project is licensed under the GNU General Public License v3.0 (GPLv3) – see the [LICENSE](LICENSE) file for details.
