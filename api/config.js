// api/config.js — Vercel serverless function
// Site configuration stored as a single JSON file committed to the repo
// (src/data/config.json), edited through the GitHub Contents API. No database.
//
// GET  /api/config   → public. Returns the current config JSON.
// POST /api/config   → admin only (Bearer ADMIN_PASS). Body: { section, value }.
//                      Merges one section and commits the file, which triggers a
//                      Vercel redeploy so the change goes live in ~1 min.
//
// Required env vars in Vercel:
//   ADMIN_PASS     → shared admin password (must match the Admin panel).
//   GITHUB_TOKEN   → token with repo write access.
//   GITHUB_OWNER   → repo owner (default: andina-ia).
//   GITHUB_REPO    → repo name (default: qbox).
//   GITHUB_BRANCH  → branch to commit to (default: main).

const SECTIONS = ['content', 'cotizador', 'clients', 'seo', 'media'];
const CONFIG_PATH = 'src/data/config.json';

function ghConfig() {
  return {
    token: process.env.GITHUB_TOKEN,
    owner: process.env.GITHUB_OWNER || 'andina-ia',
    repo: process.env.GITHUB_REPO || 'qbox',
    branch: process.env.GITHUB_BRANCH || 'main'
  };
}

function contentsUrl({ owner, repo }) {
  return `https://api.github.com/repos/${owner}/${repo}/contents/${CONFIG_PATH}`;
}

async function readConfigFile(gh) {
  const res = await fetch(`${contentsUrl(gh)}?ref=${gh.branch}`, {
    headers: { Authorization: `token ${gh.token}`, Accept: 'application/vnd.github+json' }
  });
  if (res.status === 404) return { config: {}, sha: null };
  if (!res.ok) throw new Error(await res.text());
  const data = await res.json();
  const decoded = Buffer.from(data.content, 'base64').toString('utf8');
  return { config: JSON.parse(decoded), sha: data.sha };
}

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.status(200).end();

  const gh = ghConfig();
  if (!gh.token) {
    return res.status(500).json({ error: 'GITHUB_TOKEN not configured' });
  }

  // ── READ ──────────────────────────────────────────────
  if (req.method === 'GET') {
    try {
      const { config } = await readConfigFile(gh);
      res.setHeader('Cache-Control', 's-maxage=60, stale-while-revalidate=300');
      return res.status(200).json(config);
    } catch (err) {
      return res.status(500).json({ error: 'Config read error', detail: err.message });
    }
  }

  // ── WRITE ─────────────────────────────────────────────
  if (req.method === 'POST') {
    const auth = req.headers['authorization'];
    if (!process.env.ADMIN_PASS || auth !== `Bearer ${process.env.ADMIN_PASS}`) {
      return res.status(401).json({ error: 'Unauthorized' });
    }

    const { section, value } = req.body || {};
    if (!section || !SECTIONS.includes(section)) {
      return res.status(400).json({ error: `Invalid section. Allowed: ${SECTIONS.join(', ')}` });
    }
    if (value === undefined) {
      return res.status(400).json({ error: 'Missing field: value' });
    }

    // Read → merge → commit. Retry once if the SHA is stale (concurrent edit).
    for (let attempt = 0; attempt < 2; attempt++) {
      try {
        const { config, sha } = await readConfigFile(gh);
        config[section] = value;
        const body = {
          message: `Admin: actualizar config (${section})`,
          content: Buffer.from(JSON.stringify(config, null, 2) + '\n', 'utf8').toString('base64'),
          branch: gh.branch,
          ...(sha ? { sha } : {})
        };
        const put = await fetch(contentsUrl(gh), {
          method: 'PUT',
          headers: {
            Authorization: `token ${gh.token}`,
            'Content-Type': 'application/json',
            Accept: 'application/vnd.github+json'
          },
          body: JSON.stringify(body)
        });
        if (put.status === 409 && attempt === 0) continue; // stale sha, retry
        if (!put.ok) throw new Error(await put.text());
        return res.status(200).json({ ok: true, section });
      } catch (err) {
        if (attempt === 1) {
          return res.status(500).json({ error: 'Config write error', detail: err.message });
        }
      }
    }
  }

  return res.status(405).json({ error: 'Method not allowed' });
}
