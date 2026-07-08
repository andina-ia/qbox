// api/config.js — Vercel serverless function
// GET  /api/config          → public. Returns stored site config as { content, cotizador, clients, seo, media }.
//                             Sections that were never saved are simply absent, so landing pages keep their
//                             hardcoded defaults as fallback.
// POST /api/config          → admin only (Bearer ADMIN_PASS). Body: { section, value }. Upserts one section.
//
// Required env vars in Vercel:
//   SUPABASE_URL               → https://xnycltgfyvvguyqkuczo.supabase.co
//   SUPABASE_SERVICE_ROLE_KEY  → service_role key (server-side only, never exposed)
//   ADMIN_PASS                 → same password used by the Admin panel / upload.js

const SECTIONS = ['content', 'cotizador', 'clients', 'seo', 'media'];

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.status(200).end();

  const SUPABASE_URL = process.env.SUPABASE_URL || 'https://xnycltgfyvvguyqkuczo.supabase.co';
  const SERVICE_KEY  = process.env.SUPABASE_SERVICE_ROLE_KEY;

  if (!SERVICE_KEY) {
    return res.status(500).json({ error: 'SUPABASE_SERVICE_ROLE_KEY not configured' });
  }

  const restUrl = `${SUPABASE_URL}/rest/v1/site_config`;
  const sbHeaders = {
    apikey: SERVICE_KEY,
    Authorization: `Bearer ${SERVICE_KEY}`,
    'Content-Type': 'application/json'
  };

  // ── READ ──────────────────────────────────────────────
  if (req.method === 'GET') {
    try {
      const r = await fetch(`${restUrl}?select=key,value`, { headers: sbHeaders });
      if (!r.ok) throw new Error(await r.text());
      const rows = await r.json();
      const config = {};
      rows.forEach(row => { config[row.key] = row.value; });
      // Short CDN cache so visitors get fresh config without hammering the DB.
      res.setHeader('Cache-Control', 's-maxage=60, stale-while-revalidate=300');
      return res.status(200).json(config);
    } catch (err) {
      return res.status(500).json({ error: 'Supabase read error', detail: err.message });
    }
  }

  // ── WRITE ─────────────────────────────────────────────
  if (req.method === 'POST') {
    const auth = req.headers['authorization'];
    if (auth !== `Bearer ${process.env.ADMIN_PASS || 'qbox2026'}`) {
      return res.status(401).json({ error: 'Unauthorized' });
    }

    const { section, value } = req.body || {};
    if (!section || !SECTIONS.includes(section)) {
      return res.status(400).json({ error: `Invalid section. Allowed: ${SECTIONS.join(', ')}` });
    }
    if (value === undefined) {
      return res.status(400).json({ error: 'Missing field: value' });
    }

    try {
      const r = await fetch(restUrl, {
        method: 'POST',
        headers: { ...sbHeaders, Prefer: 'resolution=merge-duplicates,return=minimal' },
        body: JSON.stringify([{ key: section, value, updated_at: new Date().toISOString() }])
      });
      if (!r.ok) throw new Error(await r.text());
      return res.status(200).json({ ok: true, section });
    } catch (err) {
      return res.status(500).json({ error: 'Supabase write error', detail: err.message });
    }
  }

  return res.status(405).json({ error: 'Method not allowed' });
}
