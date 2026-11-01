---
title: Static vs server rendering
description: When Astro builds HTML ahead of time, and when it renders on request.
date: 2026-10-30
---

By default Astro builds every page to plain HTML when you run `npm run build`. That output sits in the `dist` folder and any host can serve it, including GitHub Pages.

Server rendering needs an adapter, such as `@astrojs/node`, and a host that runs code. GitHub Pages can't do that, so this site stays static.
