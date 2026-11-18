// Shared content for the demo coffee roaster site.
// Northbank Roasters is a made-up business used for learning Astro.

// Unsplash images are hotlinked, as Unsplash asks. This builds a sized URL.
export function unsplash(id: string, width = 1200, height?: number) {
  const params = new URLSearchParams({
    auto: 'format',
    fit: 'crop',
    q: '75',
    w: String(width),
  });
  if (height) params.set('h', String(height));
  return `https://images.unsplash.com/${id}?${params}`;
}

export type Roast = 'Light' | 'Medium' | 'Dark';

export interface Product {
  slug: string;
  name: string;
  origin: string;
  roast: Roast;
  notes: string[];
  price: number;
  image: string;
  alt: string;
  credit: { name: string; url: string };
}

export const products: Product[] = [
  {
    slug: 'sunrise-ethiopia',
    name: 'Sunrise',
    origin: 'Yirgacheffe, Ethiopia',
    roast: 'Light',
    notes: ['Jasmine', 'Lemon', 'Honey'],
    price: 11.5,
    image: 'photo-1540965555-ef9a836372ed',
    alt: 'Roasted coffee beans spilling across a white surface',
    credit: { name: 'Phil Desforges', url: 'https://unsplash.com/@storybyphil' },
  },
  {
    slug: 'house-blend',
    name: 'House Blend',
    origin: 'Brazil and Colombia',
    roast: 'Medium',
    notes: ['Milk chocolate', 'Hazelnut', 'Caramel'],
    price: 9.5,
    image: 'photo-1497935586351-b67a49e012bf',
    alt: 'Coffee beans next to ground coffee on a wooden board',
    credit: { name: 'Nathan Dumlao', url: 'https://unsplash.com/@nate_dumlao' },
  },
  {
    slug: 'harbour-espresso',
    name: 'Harbour Espresso',
    origin: 'Sumatra and Guatemala',
    roast: 'Dark',
    notes: ['Cocoa', 'Molasses', 'Spice'],
    price: 10.5,
    image: 'photo-1737064004501-6fc2b23d20d6',
    alt: 'A pile of dark roasted coffee beans',
    credit: { name: 'Maria Kovalets', url: 'https://unsplash.com/@marylooo' },
  },
  {
    slug: 'huila-single-origin',
    name: 'Huila',
    origin: 'Huila, Colombia',
    roast: 'Medium',
    notes: ['Red apple', 'Brown sugar', 'Orange'],
    price: 12,
    image: 'photo-1587049016823-69ef9d68bd44',
    alt: 'Medium roast coffee beans on a white surface',
    credit: { name: 'Mockup Graphics', url: 'https://unsplash.com/@mockupgraphics' },
  },
];

export const features = [
  {
    icon: 'flame',
    title: 'Roasted weekly',
    text: 'We roast every Monday in small batches, so your coffee ships within 4 days of roasting.',
  },
  {
    icon: 'leaf',
    title: 'Traceable farms',
    text: 'Every bag names the farm or co-op it came from, and what we paid for the green beans.',
  },
  {
    icon: 'truck',
    title: 'Free UK delivery',
    text: 'Free tracked delivery on orders over £25. Letterbox-friendly bags, so no missed parcels.',
  },
  {
    icon: 'package',
    title: 'Compostable bags',
    text: 'Our bags and labels go in your home compost. The valve is the only part to bin.',
  },
];

export const stats = [
  { value: '4', label: 'days from roaster to your door' },
  { value: '12', label: 'farms we buy from directly' },
  { value: '250g', label: 'bags, whole bean or ground' },
  { value: '£25', label: 'free delivery threshold' },
];

export const brewGuides = [
  {
    title: 'Pour over',
    time: '3 min',
    ratio: '15g to 250ml',
    image: 'photo-1573518378653-55e125da29e5',
    alt: 'Coffee dripping through a glass pour-over brewer',
    credit: { name: 'Jamie Long', url: 'https://unsplash.com/@jamievlong' },
    steps: ['Rinse the paper filter', 'Bloom with 40ml for 30 seconds', 'Pour slowly in circles'],
  },
  {
    title: 'Cafetière',
    time: '4 min',
    ratio: '30g to 500ml',
    image: 'photo-1592243503355-5183f4c8f689',
    alt: 'A steel kettle beside a coffee brewer on a wooden table',
    credit: { name: 'Elisa Hane', url: 'https://unsplash.com/@withoutwasteshop' },
    steps: ['Use a coarse grind', 'Add water just off the boil', 'Plunge slowly after 4 minutes'],
  },
];

export const faqs = [
  {
    q: 'Whole bean or ground?',
    a: 'Whole bean stays fresh for longer. If you pick ground, tell us your brewer at checkout and we grind to suit it.',
  },
  {
    q: 'How long does coffee stay fresh?',
    a: 'It tastes best from 7 to 30 days after roasting. Keep the bag sealed, away from light and out of the fridge.',
  },
  {
    q: 'Can I pause my subscription?',
    a: 'Yes. Skip a delivery or pause for up to 3 months from your account, with no fee.',
  },
  {
    q: 'Do you supply cafés?',
    a: 'We do. Get in touch through the contact page and we will send our wholesale price list.',
  },
];

export const hero = {
  image: 'photo-1773118360501-00f979fd9322',
  alt: 'Freshly roasted coffee beans turning in a roaster drum',
  credit: { name: 'Ichsan Chairul', url: 'https://unsplash.com/@__humanbean' },
};

export const story = {
  image: 'photo-1607681034540-2c46cc71896d',
  alt: 'A roaster stirring freshly roasted coffee beans with a metal scoop',
  credit: { name: 'Tim Mossholder', url: 'https://unsplash.com/@timmossholder' },
};

export const formatPrice = (n: number) =>
  new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(n);
