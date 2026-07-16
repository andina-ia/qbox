import { defineConfig } from "astro/config";

// Static site. The serverless functions live in /api and are handled by Vercel
// directly (they are not Astro endpoints), so no SSR adapter is needed.
// Directory format ("/admin" -> admin/index.html) so clean URLs resolve on
// static hosting without extra rewrites.
export default defineConfig({
  site: "https://qboxmodular.com.ar",
  base: "/sanjuan",
});
