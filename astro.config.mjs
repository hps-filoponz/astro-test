// @ts-check
import { defineConfig } from 'astro/config';

// One repo, two deploy targets. The workflow sets DEPLOY_TARGET.
//   github (default): https://hps-filoponz.github.io/astro-test/
//   20i:              https://astrotest-com.stackstaging.com/
const targets = {
  github: { site: 'https://hps-filoponz.github.io', base: '/astro-test' },
  '20i': { site: 'https://astrotest-com.stackstaging.com', base: '/' },
};

const target = process.env.DEPLOY_TARGET === '20i' ? '20i' : 'github';

export default defineConfig(targets[target]);
