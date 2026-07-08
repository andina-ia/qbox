// api/upload.js — Vercel serverless function
// Recibe: { filename, contentBase64, category }
// Sube la imagen a GitHub repo en assets/media/{category}/{filename}
// Variables de entorno requeridas en Vercel:
//   GITHUB_TOKEN   → token con permisos repo
//   GITHUB_OWNER   → guadalupelloveras-pixel
//   GITHUB_REPO    → Qbox

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });

  // Auth — same password as Admin panel
  const auth = req.headers['authorization'];
  if (auth !== `Bearer ${process.env.ADMIN_PASS || 'qbox2026'}`) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const { filename, contentBase64, category } = req.body || {};
  if (!filename || !contentBase64 || !category) {
    return res.status(400).json({ error: 'Missing fields: filename, contentBase64, category' });
  }

  const TOKEN  = process.env.GITHUB_TOKEN;
  const OWNER  = process.env.GITHUB_OWNER  || 'guadalupelloveras-pixel';
  const REPO   = process.env.GITHUB_REPO   || 'Qbox';

  if (!TOKEN) return res.status(500).json({ error: 'GITHUB_TOKEN not configured' });

  // Sanitize filename
  const safe = filename.replace(/[^a-zA-Z0-9._-]/g, '_');
  const path = `assets/media/${category}/${safe}`;
  const apiUrl = `https://api.github.com/repos/${OWNER}/${REPO}/contents/${path}`;

  // Check if file already exists (need SHA to update)
  let sha;
  try {
    const check = await fetch(apiUrl, {
      headers: { Authorization: `token ${TOKEN}`, Accept: 'application/vnd.github+json' }
    });
    if (check.ok) {
      const existing = await check.json();
      sha = existing.sha;
    }
  } catch {}

  // Upload to GitHub
  const body = {
    message: `Admin: subir imagen ${safe} a ${category}`,
    content: contentBase64,
    ...(sha ? { sha } : {})
  };

  const ghRes = await fetch(apiUrl, {
    method: 'PUT',
    headers: {
      Authorization: `token ${TOKEN}`,
      'Content-Type': 'application/json',
      Accept: 'application/vnd.github+json'
    },
    body: JSON.stringify(body)
  });

  if (!ghRes.ok) {
    const err = await ghRes.json();
    return res.status(500).json({ error: 'GitHub error', detail: err.message });
  }

  const data = await ghRes.json();
  const publicUrl = `https://raw.githubusercontent.com/${OWNER}/${REPO}/main/${path}`;

  return res.status(200).json({ url: publicUrl, path, sha: data.content?.sha });
}
