// Shared site-wide constants and helpers.

export const WA_NUMBER = '5492644431309';
export const CONTACT_EMAIL = 'comercial@qboxmodular.com.ar';
export const MAPS_URL =
  'https://www.google.com/maps/search/?api=1&query=Rivadavia%2C+San+Juan%2C+Argentina';

const BASE = (import.meta.env.BASE_URL || '/').replace(/\/$/, '') || '/';

export function withBase(path = '/') {
  if (!path || path === '/') return `${BASE}/`;
  const normalizedPath = path.startsWith('/') ? path.slice(1) : path;
  return `${BASE}/${normalizedPath}`;
}

export function waLink(text = 'Hola qBox, quiero hacer una consulta.') {
  return `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(text)}`;
}
