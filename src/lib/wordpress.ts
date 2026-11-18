// Fetches Coffee posts from the headless WordPress install.
// WordPress: Meta Box CPT "coffee" with fields origin, roast, price, notes.

import { products, unsplash } from '../data/site';

export const CMS_URL = process.env.CMS_URL ?? 'https://cms-astrotest-com.stackstaging.com';

// In CI we set CMS_REQUIRED=true, so a failed fetch stops the build
// instead of deploying the sample data by mistake.
const CMS_REQUIRED = process.env.CMS_REQUIRED === 'true';

export type Roast = 'Light' | 'Medium' | 'Dark';

export interface CoffeeImage {
  src: string;
  srcset?: string;
  width: number;
  height: number;
  alt: string;
}

export interface CoffeeEntry {
  id: string; // slug, used in the URL
  name: string;
  origin: string;
  roast: Roast;
  price: number;
  notes: string[];
  description: string; // HTML from the WordPress editor
  image: CoffeeImage | null;
  updated: string;
  source: 'wordpress' | 'sample';
}

// Minimal shape of the WordPress REST response we rely on
interface WpSize {
  source_url: string;
  width: number;
  height: number;
}
interface WpCoffee {
  slug: string;
  modified_gmt: string;
  title: { rendered: string };
  content: { rendered: string };
  meta_box?: { origin?: string; roast?: string; price?: string | number; notes?: string[] | string };
  _embedded?: {
    'wp:featuredmedia'?: Array<{
      source_url: string;
      alt_text?: string;
      media_details?: { width: number; height: number; sizes?: Record<string, WpSize> };
    }>;
  };
}

const entities: Record<string, string> = {
  '&amp;': '&', '&#038;': '&', '&#8217;': '’', '&#8216;': '‘',
  '&#8220;': '“', '&#8221;': '”', '&#8211;': '-', '&#8212;': '-',
  '&quot;': '"', '&#039;': "'", '&nbsp;': ' ',
};
const decode = (s: string) => s.replace(/&[#\w]+;/g, (m) => entities[m] ?? m);

const toRoast = (value?: string): Roast =>
  value === 'Light' || value === 'Dark' ? value : 'Medium';

function toImage(wp: WpCoffee, name: string): CoffeeImage | null {
  const media = wp._embedded?.['wp:featuredmedia']?.[0];
  if (!media?.source_url) return null;
  const sizes = Object.values(media.media_details?.sizes ?? {});
  const srcset = sizes
    .filter((s) => s.width >= 300)
    .map((s) => `${s.source_url} ${s.width}w`)
    .join(', ');
  return {
    src: media.source_url,
    srcset: srcset || undefined,
    width: media.media_details?.width ?? 800,
    height: media.media_details?.height ?? 600,
    alt: media.alt_text || name,
  };
}

function toEntry(wp: WpCoffee): CoffeeEntry {
  const mb = wp.meta_box ?? {};
  const name = decode(wp.title.rendered);
  const notes = Array.isArray(mb.notes) ? mb.notes : mb.notes ? [mb.notes] : [];
  return {
    id: wp.slug,
    name,
    origin: mb.origin ?? '',
    roast: toRoast(mb.roast),
    price: Number(mb.price) || 0,
    notes: notes.filter(Boolean),
    description: wp.content.rendered,
    image: toImage(wp, name),
    updated: wp.modified_gmt,
    source: 'wordpress',
  };
}

async function fetchAllCoffee(): Promise<WpCoffee[]> {
  const all: WpCoffee[] = [];
  let page = 1;
  let totalPages = 1;
  do {
    const res = await fetch(`${CMS_URL}/wp-json/wp/v2/coffee?per_page=100&page=${page}&_embed=wp:featuredmedia`, {
      signal: AbortSignal.timeout(20_000),
    });
    if (!res.ok) throw new Error(`WordPress returned ${res.status} for page ${page}`);
    totalPages = Number(res.headers.get('X-WP-TotalPages') ?? 1);
    all.push(...((await res.json()) as WpCoffee[]));
    page++;
  } while (page <= totalPages);
  return all;
}

// Sample data from src/data/site.ts, used for local builds when WordPress is unreachable
function sampleCoffee(): CoffeeEntry[] {
  return products.map((p) => ({
    id: p.slug,
    name: p.name,
    origin: p.origin,
    roast: p.roast,
    price: p.price,
    notes: p.notes,
    description: `<p>Sample coffee. Start WordPress or check CMS_URL to see live data.</p>`,
    image: {
      src: unsplash(p.image, 800, 600),
      srcset: `${unsplash(p.image, 400, 300)} 400w, ${unsplash(p.image, 800, 600)} 800w`,
      width: 800,
      height: 600,
      alt: p.alt,
    },
    updated: new Date().toISOString(),
    source: 'sample',
  }));
}

export async function loadCoffee(): Promise<CoffeeEntry[]> {
  try {
    const items = await fetchAllCoffee();
    console.log(`[wordpress] Loaded ${items.length} coffees from ${CMS_URL}`);
    return items.map(toEntry);
  } catch (err) {
    if (CMS_REQUIRED) throw err;
    console.warn(`[wordpress] Could not reach ${CMS_URL} (${(err as Error).message}). Using sample data.`);
    return sampleCoffee();
  }
}
