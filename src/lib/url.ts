// Builds links that respect the "base" setting in astro.config.mjs.
// url('/about/') -> '/astro-test/about/'
export function url(path = '/') {
  const base = import.meta.env.BASE_URL.replace(/\/$/, '');
  const clean = path.startsWith('/') ? path : `/${path}`;
  return `${base}${clean}`;
}
