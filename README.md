# qBox — Sitio web

Sitio de qBox construido con **[Astro](https://astro.build/)** (SSG estático).
Componentes reutilizables, CSS separado y sin base de datos: la configuración
editable del sitio vive en un único `src/data/config.json` versionado en git.

## Requisitos

- Node 18+ y npm.

## Desarrollo

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # genera dist/ (estático)
npm run preview  # sirve el build
```

## Estructura

| Ruta | Qué es |
|---|---|
| `src/pages/index.astro` | **Home** (hero, soluciones, nosotros, proyectos, confían, cotizador, showroom) |
| `src/pages/soluciones.astro` | Línea residencial / corporativa (`/soluciones?tipo=residencial` · `?tipo=corporativo`) |
| `src/pages/categoria.astro` | Landing por tipo de proyecto (`/categoria?cat=modulos` · `stands` · `cocheras-galerias` · `ampliaciones` · `locales`) |
| `src/pages/admin.astro` | **Panel de administración** de contenido (`/admin`) |
| `src/components/` | Componentes compartidos: `Nav`, `Footer`, `WhatsAppButton`, `BaseHead` |
| `src/layouts/BaseLayout.astro` | Layout base (head + nav + slot + footer + whatsapp + analytics) |
| `src/styles/global.css` | Design tokens y estilos compartidos (header, nav, footer, botones, etc.) |
| `src/data/config.json` | **Configuración editable del sitio** (textos, tarifas, marcas, SEO, medios) |
| `api/config.js` | Función serverless: lee/escribe `config.json` vía GitHub API |
| `api/upload.js` | Función serverless: sube imágenes al repo (`public/assets/media/`) |
| `public/assets/` | Imágenes, video (`hero.mp4`), logos y favicons |

## Configuración del sitio (sin base de datos)

La config editable vive en `src/data/config.json` y se **hornea en build**: cada
página Astro la importa y renderiza los textos/tarifas/marcas/SEO directamente en
el HTML (no hay fetch en runtime).

El panel `/admin` edita esa config. Al guardar:

1. `POST /api/config` con `{ section, value }` (header `Authorization: Bearer <ADMIN_PASS>`).
2. La función commitea `src/data/config.json` en el repo vía GitHub API.
3. El commit dispara un redeploy en Vercel → el cambio queda publicado en ~1 min.

Las imágenes que se suben desde **Medios** se commitean en `public/assets/media/`
y su URL se guarda en `config.json` (sección `media`).

> Seguridad: el login del Admin (correo + clave) es solo una barrera de UX en el
> cliente. El control real es la validación del `Bearer <ADMIN_PASS>` en las
> funciones serverless. Mantené `ADMIN_PASS` en secreto en Vercel.

## Variables de entorno (Vercel)

Ver `.env.example`. Necesarias para que el Admin pueda guardar:

- `ADMIN_PASS` — clave compartida (debe coincidir con la del panel).
- `GITHUB_TOKEN` — token con permisos de escritura sobre el repo.
- `GITHUB_OWNER` (`andina-ia`), `GITHUB_REPO` (`qbox`), `GITHUB_BRANCH` (`main`).

## Analytics

Las páginas registran eventos (visitas, uso del cotizador, clics a WhatsApp y
showroom) en una tabla `events` de Supabase usando una publishable key desde el
cliente. El dashboard del Admin los lee de ahí. Esto es independiente de la
configuración del sitio.

## Deploy (Vercel)

- Framework preset: **Astro** (autodetectado). Build command `astro build`,
  output `dist/`. Las funciones de `api/` se despliegan automáticamente.
- Configurar las variables de entorno listadas arriba.
