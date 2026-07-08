// api/upload.js — Vercel serverless function
// Recibe: { filename, contentBase64, category }
// Sube la imagen a GitHub repo en public/assets/media/{category}/{filename}
// Variables de entorno requeridas en Vercel:
//   ADMIN_PASS     → shared admin password (must match the Admin panel)
//   GITHUB_TOKEN   → token con permisos repo
//   GITHUB_OWNER   → repo owner (default: andina-ia)
//   GITHUB_REPO    → repo name (default: qbox)
//   GITHUB_BRANCH  → branch to commit to (default: main)

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });

  // Auth — same password as Admin panel
  const auth = req.headers['authorization'];
  if (!process.env.ADMIN_PASS || auth !== `Bearer ${process.env.ADMIN_PASS}`) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const { filename, contentBase64, category } = req.body || {};
  if (!filename || !contentBase64 || !category) {
    return res.status(400).json({ error: 'Missing fields: filename, contentBase64, category' });
  }

  const TOKEN  = process.env.GITHUB_TOKEN;
  const OWNER  = process.env.GITHUB_OWNER  || 'andina-ia';
  const REPO   = process.env.GITHUB_REPO   || 'qbox';
  const BRANCH = process.env.GITHUB_BRANCH || 'main';

  if (!TOKEN) return res.status(500).json({ error: 'GITHUB_TOKEN not configured' });

  // Sanitize filename. Images live in public/ so they are served statically.
  const safe = filename.replace(/[^a-zA-Z0-9._-]/g, '_');
  const path = `public/assets/media/${category}/${safe}`;
  const apiUrl = `https://api.github.com/repos/${OWNER}/${REPO}/contents/${path}`;

  // Check if file already exists (need SHA to update)
  let sha;
  try {
    const check = await fetch(`${apiUrl}?ref=${BRANCH}`, {
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
    branch: BRANCH,
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
  // Served immediately from GitHub raw (available before the redeploy finishes).
  const publicUrl = `https://raw.githubusercontent.com/${OWNER}/${REPO}/${BRANCH}/${path}`;

  return res.status(200).json({ url: publicUrl, path, sha: data.content?.sha });
}
