# qBox Agent Instructions

## Project Overview

**qBox** is a static marketing website built with [Astro](https://astro.build/) and deployed on Vercel. It has no database—instead, it uses a **configuration-driven CMS** where editable content lives in `src/data/config.json`, baked into the build. An admin panel at `/admin` allows non-developers to edit content and upload images via serverless functions that commit directly to GitHub.

**Key Points:**
- **SSG Only**: All pages pre-rendered at build time; no SSR adapter
- **Config-Driven**: Single source of truth in `src/data/config.json`
- **GitHub as CMS**: Admin changes commit via GitHub API, triggering automatic Vercel redeploy (~1 min)
- **Image Hosting**: Uploads go to `public/assets/media/` via serverless function

---

## Build & Dev Commands

```bash
npm install              # Install dependencies (just Astro)
npm run dev             # Dev server on http://localhost:4321
npm run build           # Generate static site → dist/
npm run preview         # Preview the build locally
npm run astro [command] # Run raw Astro commands
```

---

## Architecture

### Pages & Routes

| File | Purpose | Key Pattern |
|------|---------|------------|
| `src/pages/index.astro` | Home (hero, solutions, projects, testimonials) | Hardcoded sections with config text |
| `src/pages/soluciones.astro` | Solutions by type (residencial/corporativo) | Query-param driven: `?tipo=residencial` |
| `src/pages/categoria.astro` | Product categories (módulos, stands, cocheras, etc.) | Dynamic with `CATS` data constant |
| `src/pages/admin.astro` | Admin panel (login, edit content/prices, upload media) | Embedded UI with inline styles & scripts |
| `src/layouts/BaseLayout.astro` | Main layout (nav + slot + footer + analytics) | Wraps all pages; accepts `seo` and `home` props |

### Components

All components are `.astro` files (server-side only, no client state):

| Component | Role | Props |
|-----------|------|-------|
| `BaseHead.astro` | Meta tags, fonts, favicons | — |
| `Nav.astro` | Header with hamburger menu | `home` (bool) |
| `Footer.astro` | Footer with showroom info | `home` (bool) |
| `WhatsAppButton.astro` | Fixed FAB link to WhatsApp | — |

**Pattern:** Pages use `<BaseLayout seo={seo} home={true}>` which auto-includes all components.

### Styling

**Single file:** `src/styles/global.css` (~400 lines, no Tailwind/CSS-in-JS)

**Design tokens** (CSS variables in `:root`):
- Colors: `--bg` (light), `--ink` (dark), `--accent` (brown)
- Fonts: `--serif` (Newsreader), `--sans` (Hanken Grotesk)
- Layout: `--max` (1240px), `--px` (responsive padding via clamp)

**Key classes:**
- `.rv` + `.in` — reveal-on-scroll animation (via IntersectionObserver in BaseLayout)
- `.d1`, `.d2`, `.d3` — stagger delays for cascading reveals
- `.btn-p`, `.btn-s` — primary/secondary buttons
- `.eye` — small caps label styling

**Responsive:** Mobile-first, breakpoints at 900px and 640px (hamburger menu at <900px).

### Configuration & Data Flow

**`src/data/config.json`** contains:
```json
{
  "content": { "heroSub": "...", "nosotrosP1": "...", ... },
  "cotizador": { "rateModulo": 640, "rateSemi": 135 },
  "clients": [{ "name": "Mapal", "active": false }, ...],
  "seo": {
    "inicio": { "title": "...", "desc": "...", "keywords": "..." },
    "modulos": {...},
    ...
  },
  "media": {}
}
```

**Pattern:** Import in pages and destructure what you need:
```javascript
import config from '../data/config.json';
const seo = config.seo.inicio;
const content = config.content;
```

**`src/lib/site.js`** exports shared constants (WhatsApp number, email, helper functions).

---

## API & Serverless Functions

**Environment variables** (required in Vercel):
- `ADMIN_PASS` — admin panel password
- `GITHUB_TOKEN` — repo write access
- `GITHUB_OWNER`, `GITHUB_REPO`, `GITHUB_BRANCH` — repo details

### GET `/api/config`
Returns current `src/data/config.json` (public, cached 60s).

### POST `/api/config`
Admin-only. Updates a config section and commits to GitHub via GitHub API.
```javascript
POST /api/config
Authorization: Bearer <ADMIN_PASS>
{ section: 'content'|'cotizador'|'clients'|'seo'|'media', value: {...} }
```
Triggers Vercel redeploy; changes live in ~1 min.

### POST `/api/upload`
Admin-only. Uploads images to `public/assets/media/{category}/{filename}`.
```javascript
POST /api/upload
Authorization: Bearer <ADMIN_PASS>
{ filename, contentBase64, category }
```
Returns GitHub URL (available on CDN immediately).

---

## Common Development Patterns

### Adding a New Page

1. Create `src/pages/mypage.astro`
2. Import config and lib constants:
   ```javascript
   import BaseLayout from '../layouts/BaseLayout.astro';
   import config from '../data/config.json';
   
   const seo = config.seo.mypage; // add to config.json first
   ```
3. Wrap with `<BaseLayout>` and use `<slot />`
4. Reference config.content, clients, cotizador as needed
5. Use `.rv` + `.d1/.d2/.d3` classes for reveal animations
6. Use CSS variables for colors/spacing

### Editing Content

1. Update `src/data/config.json` directly OR
2. Use admin panel (`/admin`) to edit content/prices, which commits to GitHub

### Adding Images

- Static images: Place in `public/assets/` and reference in HTML
- Admin-uploaded images: Admin panel saves to `public/assets/media/` with URL in `config.json`

### Query-Based Pages

For dynamic routes (e.g., `?tipo=residencial`), parse on the client:
```javascript
<script>
  const params = new URLSearchParams(window.location.search);
  const tipo = params.get('tipo');
  // Show/hide sections based on tipo
</script>
```

---

## Key Gotchas & Best Practices

- **No SSR**: All pages are static HTML. Dynamic behavior requires query params + client-side parsing.
- **Build-time rendering**: Content updates require a commit or redeploy. Use admin panel for changes that should go live fast.
- **Admin panel hidden**: `/admin` is not linked from nav; access directly or via password.
- **No database**: GitHub is the CMS. Commits via API trigger redeploys.
- **Clean URLs**: Directory format (`/admin` → `admin/index.html`) ensures Vercel static hosting resolves correctly.
- **Reveal animations**: Add `.rv` + `.d1/.d2/.d3` to elements you want to animate on scroll; IntersectionObserver in BaseLayout handles triggering.
- **Responsive units**: Use `clamp()` for fluid sizing, not media queries for size changes.

---

## When Adding Features

1. **Check config.json first**: Can the feature be data-driven? If yes, add to config and update admin panel.
2. **Reuse components**: Nav, Footer, WhatsAppButton are shared; avoid duplicating.
3. **Update layout last**: If all pages need the feature, add to `BaseLayout.astro`.
4. **Test locally**: `npm run dev` first, then `npm run build && npm run preview`.
5. **Deployment**: Push to GitHub; Vercel redeploys automatically. Config changes trigger separate redeploy.

---

## Project Files at a Glance

| Path | Purpose |
|------|---------|
| `src/pages/` | All page routes (.astro files) |
| `src/components/` | Reusable components (Nav, Footer, etc.) |
| `src/layouts/BaseLayout.astro` | Main layout wrapper |
| `src/styles/global.css` | All site styles (no CSS modules/Tailwind) |
| `src/data/config.json` | **Editable CMS content** |
| `src/lib/site.js` | Shared constants & utilities |
| `api/config.js` | Serverless: config read/write via GitHub API |
| `api/upload.js` | Serverless: image upload to GitHub |
| `public/assets/` | Static images, videos, favicons |
| `astro.config.mjs` | Astro configuration (minimal, SSG only) |
| `package.json` | Dependencies (just Astro) |
