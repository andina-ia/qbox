# qBox — Sitio web completo

Sitio estático **multipágina**, listo para publicar. Cada archivo `.html` es
**autónomo**: todo (HTML, CSS, JS, fuentes, logo, video e imágenes) está embebido
dentro del propio archivo. **No requiere build, dependencias ni servidor.**

## Estructura

| Archivo | Qué es | Cómo se llega |
|---|---|---|
| `index.html` | **Home** (hero, soluciones, quiénes somos, proyectos, confían, cotizador, showroom) | Página principal |
| `Soluciones.html` | Explicación de cada línea — se abre **aparte** | `Soluciones.html?tipo=residencial` y `?tipo=corporativo` (desde las tarjetas "¿Qué necesita construir?") |
| `Categoria.html` | **Landing por tipo de proyecto terminado** (una sola página que sirve los 5 tipos) | `Categoria.html?cat=modulos` · `stands` · `cocheras-galerias` · `ampliaciones` · `locales` (desde el bento "Proyectos terminados") |
| `Admin.html` | **Panel de administración** de contenido | enlace "Administración" en el pie del home |

Todas las páginas enlazan entre sí con rutas relativas, así que **funcionan
tanto en GitHub Pages / Vercel como abiertas localmente**.

## Integraciones (ya funcionando)
- **WhatsApp** → botón flotante + CTAs, número `+54 9 264 443-1309` (`wa.me`).
- **Calendly / Agendar visita** → modal de reserva de turno en el showroom (botones "Agendar Cita en Showroom").
- **Google Maps** → dirección del showroom en el pie del home.
- **Admin** → login con correo `comercial@qboxmodular.com.ar` + clave. Edita textos,
  tarifas del cotizador y marcas; los cambios se guardan en el navegador (localStorage)
  de quien administra.

## Publicar

### GitHub Pages
1. Subir **todos** los archivos de esta carpeta a la raíz del repo (que `index.html` quede en la raíz):
   ```bash
   git add .
   git commit -m "qBox — sitio completo (home, soluciones, proyectos, admin)"
   git push origin main
   ```
2. Settings → Pages → Source: `main` / `/ (root)` → Save.
3. Queda en `https://<usuario>.github.io/<repo>/`.

### Vercel
- Conectar el repo o arrastrar la carpeta. Framework preset: **Other / Static**.
  No hay build command. Output directory: la raíz (donde está `index.html`).

> ⚠️ **Importante:** subí la carpeta **completa**. Si solo subís `index.html`, las
> tarjetas de proyectos y de soluciones darán **404** (faltarían `Categoria.html`,
> `Soluciones.html` y `Admin.html`).

## Notas
- El **video del hero es externo**: `assets/hero.mp4`. `index.html` lo carga por ruta
  relativa, así que **subí siempre la carpeta `assets/` completa**. Si abrís el sitio
  localmente, hacelo con un servidor estático (`npx serve .`) — abrirlo con doble-click
  (file://) o en un preview aislado puede mostrar un cartel `[bundle] error` porque no
  encuentra el `.mp4`; en GitHub Pages / Vercel carga sin problema.
- `index.html` pesa ~2.4 MB (antes 6 MB: el video ya no va embebido). Las demás páginas son livianas.
- Imágenes optimizadas para web (lazy-load en las que no son del hero).
- No editar los `.html` a mano: están generados por un empaquetador. Para cambios de
  diseño se regeneran desde el proyecto original y se reemplazan.
- La galería de cada proyecto usa imágenes de referencia; se pueden cambiar por fotos
  reales de obra cuando estén disponibles.
