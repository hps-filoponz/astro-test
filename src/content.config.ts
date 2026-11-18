import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';
import { loadCoffee } from './lib/wordpress';

// Every Markdown file in src/content/blog becomes a blog post.
const blog = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/blog' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    date: z.coerce.date(),
    draft: z.boolean().default(false),
  }),
});

// Coffee comes from headless WordPress at build time.
// The schema checks WordPress gave us what the pages expect.
const coffee = defineCollection({
  loader: loadCoffee,
  schema: z.object({
    name: z.string(),
    origin: z.string(),
    roast: z.enum(['Light', 'Medium', 'Dark']),
    price: z.number(),
    notes: z.array(z.string()),
    description: z.string(),
    image: z
      .object({
        src: z.string(),
        srcset: z.string().optional(),
        width: z.number(),
        height: z.number(),
        alt: z.string(),
      })
      .nullable(),
    updated: z.string(),
    source: z.enum(['wordpress', 'sample']),
  }),
});

export const collections = { blog, coffee };
