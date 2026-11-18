# Astro Build: working notes

Notes from building astro-test, to turn into an "Astro Build" skill later.
Review, cut and correct before using them as the skill's source.

## To do later

- [ ] Reusable WordPress plugin: merge astro-rebuild.php, astro-headless.php and astro-forms.php
      into one plugin with a settings page (Astro URL, GitHub repo, route map per post type,
      rebuild post types, 301/302, rebuild delay, test-connection button). Token stays in wp-config.php.
- [ ] Turn astro-test into a GitHub template repo, with all per-client values in one config file.
- [ ] Draft previews (WordPress Preview button still shows the WP theme).
- [ ] Cache Astro's image folder between GitHub Actions runs once sites have many images.
- [ ] Switch redirects from 302 to 301 when a site goes live.

## Stack

- Astro 7 static output (no adapter). Plain CSS, no Tailwind.
- Hosting: 20i reseller. Astro site on a Unix (Linux) package, not WordPress-optimised.
- CMS: WordPress on a separate 20i WordPress package. Meta Box for CPTs and fields.
- Code: GitHub repo. GitHub Actions builds and deploys.

## Hosting and deploy

- Astro site: astrotest-com.stackstaging.com (20i), also hps-filoponz.github.io/astro-test (GitHub Pages).
- CMS: cms-astrotest-com.stackstaging.com.
- stackstaging.com is 20i's domain: no subdomains on it, so the CMS gets its own package and staging URL.
- One repo, two targets. DEPLOY_TARGET env picks site and base in astro.config.mjs.
  - github: base /astro-test. 20i: base /.
  - All internal links go through src/lib/url.ts so the base path works.
- deploy-20i.yml: checkout, setup-node 22, npm ci, npm run build, SamKirkland/FTP-Deploy-Action
  over FTPS to ./public_html/. Secrets: FTP_HOST (no ftp://), FTP_USERNAME, FTP_PASSWORD.
  - Turn off FTP Lock on the 20i package.
  - First run uploads everything; later runs only changed files (.ftp-deploy-sync-state.json in public_html, leave it).
  - SFTP is harder: GitHub runner IPs change, 20i may block them.
- deploy.yml: GitHub Pages via withastro/action. Repo Settings > Pages > Source: GitHub Actions.
- Both workflows: on push to main (paths-ignore wordpress/** and README), workflow_dispatch,
  and repository_dispatch type cms-update.
- public/.htaccess: ErrorDocument 404 /404.html plus cache headers. GitHub Pages ignores it.

## Claude session setup (GitHub)

- Connect GitHub in claude.ai Settings > Connectors.
- Install the Claude GitHub App on the account with access to the repo, or pushes get 403.
- Enabling GitHub Pages must be done by hand (API path blocked from the session).
- gh GraphQL is blocked; use gh api REST calls (for example to dispatch workflows).
- The session cannot reach stackstaging URLs. To inspect the CMS, run a temporary
  workflow_dispatch workflow that curls the REST API and read its log.

## Headless WordPress

- CPT registered with Show in REST. Meta Box REST API on, so fields appear under meta_box in the JSON.
- Permalinks set to Post name (needed for /wp-json/).
- Settings > Reading: discourage search engines.
- Astro loader: src/lib/wordpress.ts, used by src/content.config.ts (collection "coffee").
  - Fetches /wp-json/wp/v2/coffee?per_page=100&_embed=wp:featuredmedia, follows X-WP-TotalPages.
  - Maps meta_box fields, decodes title entities, price string to number, notes to array.
  - CMS_REQUIRED=true in CI: a failed fetch fails the build (no accidental sample-data deploy).
  - Locally falls back to sample data from src/data/site.ts.
- Pages: home (latest 4), /shop/ (with roast filter), /shop/[slug]/ via getStaticPaths.

## Rebuild on publish

- wordpress/astro-rebuild.php in wp-content/mu-plugins/.
- wp-config.php: ASTRO_GITHUB_TOKEN (fine-grained PAT, one repo, Contents read and write) and ASTRO_GITHUB_REPO.
- PAT: profile picture > Settings > Developer settings > Fine-grained tokens. Not in the GitHub mobile app.
- Queues a single WP-cron event 30s after save (Meta Box saves fields after the post; also batches edits).
- Admin bar shows last result plus "Rebuild now".
- Full loop measured: save in WP to live on 20i in about 1.5 minutes.

## Images

- CoffeeImage.astro: astro:assets Image with remote src, widths filtered to the original width, WebP.
- astro.config.mjs image.remotePatterns: CMS hostname.
- Results: 1.7 MB upload to 14 to 30 kB; 945 kB to 38 to 172 kB.
- Scoped styles do not reach child components: use .media :global(img).
- Tested locally with a mock WP API (python http.server serving wp-json/wp/v2/coffee).

## Locking down WordPress

- wordpress/astro-headless.php in mu-plugins.
- template_redirect to the Astro site (coffee to /shop/{slug}/), previews allowed for editors.
- post_type_link rewritten to Astro URLs. XML-RPC, WP sitemaps and feeds off.
- /wp/v2/users hidden from anonymous REST. X-Robots-Tag noindex on everything WP serves.

## Gotchas met

- create-astro templates could not download in the session; scaffold with npm init + npm install astro.
- astro preview reads the config at start: restart it with the same DEPLOY_TARGET as the build.
- Grid columns with images: use minmax(0, 1fr), or the image's intrinsic width squashes the other column.
- Icons from a child component need :global() selectors in the parent's scoped CSS.
