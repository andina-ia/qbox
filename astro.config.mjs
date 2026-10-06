import { defineConfig } from "astro/config";

// Sitio estático servido desde cPanel. El backend PHP vive en public/api.
// Directory format ("/admin" -> admin/index.html) so clean URLs resolve on
// static hosting without extra rewrites.
export default defineConfig({
  site: "https://qboxmodular.com.ar",
  base: "",
  // Estilos embebidos en cada página: cada HTML funciona solo, sin depender de
  // archivos CSS con nombre variable en _astro/ (evita páginas sin diseño si
  // se sube un HTML sin su CSS en cPanel).
  build: { inlineStylesheets: "always" },
});
