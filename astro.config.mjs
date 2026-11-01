// @ts-check
import { defineConfig } from 'astro/config';

// GitHub Pages serves this repo at https://USERNAME.github.io/astro-test/
// Change "site" to your username. If you add a custom domain later,
// set site to that domain and remove "base".
export default defineConfig({
  site: 'https://USERNAME.github.io',
  base: '/astro-test',
});
