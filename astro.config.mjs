import { defineConfig } from 'astro/config';

// Static site. The serverless functions live in /api and are handled by Vercel
// directly (they are not Astro endpoints), so no SSR adapter is needed.
export default defineConfig({
  site: 'https://qbox-five.vercel.app',
  build: {
    format: 'file'
  }
});
