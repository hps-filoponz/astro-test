# Astro test

A small Astro site for experiments, deployed to GitHub Pages.

## Run it locally

npm install
npm run dev       # http://localhost:4321/astro-test/
npm run build     # builds to dist/
npm run preview   # serves the built site

## Where things live

src/pages/            each file becomes a URL
src/layouts/Base.astro  shared HTML shell, meta tags and global styles
src/components/       reusable pieces (Header)
src/content/blog/     blog posts in Markdown
src/content.config.ts the schema for blog post frontmatter
astro.config.mjs      site URL and base path

## Deploying

Every push to main runs .github/workflows/deploy.yml, which builds the site
and publishes it to GitHub Pages. In the repo, set Settings > Pages > Source
to "GitHub Actions" once.
