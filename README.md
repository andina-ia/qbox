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
| `src/data/config.json` | Valores iniciales de la config (la fuente real es MySQL) |
| `public/api/` | Backend PHP + MySQL del panel (ver abajo) |
| `public/assets/` | Imágenes, video (`hero.mp4`), logos y favicons |

## Configuración del sitio (MySQL en cPanel)

El contenido editable (textos, tarifas, marcas, SEO, galerías) vive en **MySQL**.
`src/data/config.json` queda solo como valor inicial: se hornea en el HTML como
respaldo y `install.php` lo carga en la base la primera vez.

Al abrir cualquier página, `BaseLayout` pide `/api/config.php` y aplica los
valores actuales. Los cambios del panel `/admin` se ven **al instante**, sin rebuild.

| Endpoint | Qué hace |
|---|---|
| `api/install.php` | Instalación única: crea tablas, carga la config y el primer admin. **Borrar después de usar.** |
| `api/auth.php` | Login con sesión PHP (claves con `password_hash`, límite de 8 intentos / 15 min) |
| `api/config.php` | `GET` público · `POST {section, value}` solo admin (sesión + `X-CSRF-Token`) |
| `api/upload.php` | Sube imágenes a `/uploads/<categoría>/` (fuera del build, no se pisa al redeployar) |
| `api/track.php` / `api/events.php` | Analytics propios en la tabla `qbox_events` |

El código PHP está en `public/api/` y Astro lo copia tal cual a `dist/api/`.

## Instalación en cPanel

1. **Base de datos:** cPanel → Database Wizard → crear base + usuario con *ALL PRIVILEGES*.
2. **Credenciales:** copiar `public/api/_env.sample.php` a `/home/USUARIO/qbox-env.php`
   (fuera de `public_html`) y completarlo.
3. **Subir el sitio:** `npm run build` y subir el contenido de `dist/` a `public_html/`.
   No borrar nunca `public_html/uploads/` (ahí quedan las imágenes del panel).
4. **Instalar:** abrir `https://qboxmodular.com.ar/api/install.php`, crear el admin y
   **borrar `api/install.php`** del servidor.
5. Entrar a `/admin`.
