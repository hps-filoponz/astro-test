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

// Headless WordPress. Astro may download and optimise images from here.
const cmsUrl = new URL(process.env.CMS_URL ?? 'https://cms-astrotest-com.stackstaging.com');

export default defineConfig({
  ...targets[target],
  image: {
    remotePatterns: [{ protocol: cmsUrl.protocol.replace(':', ''), hostname: cmsUrl.hostname }],
  },
});
