// @ts-check
import { defineConfig } from 'astro/config';

// GitHub Pages serves this repo at https://hps-filoponz.github.io/astro-test/
// If you add a custom domain later,
// set site to that domain and remove "base".
export default defineConfig({
  site: 'https://hps-filoponz.github.io',
  base: '/astro-test',
});
