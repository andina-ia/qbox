-- qBox — site configuration store
-- Run this in the Supabase SQL editor (project xnycltgfyvvguyqkuczo).
--
-- Key/value table that mirrors the sections of the Admin panel:
--   content    -> { heroSub, nosotrosP1, nosotrosP2 }
--   cotizador  -> { rateModulo, rateSemi }
--   clients    -> [ { name, active } ]
--   seo        -> { <page>: { title, desc, keywords } }
--   media      -> { <category>: [ imageUrl, ... ] }

create table if not exists site_config (
  key        text primary key,
  value      jsonb not null,
  updated_at timestamptz not null default now()
);

alter table site_config enable row level security;

-- Public can read the configuration (landing pages).
drop policy if exists "site_config public read" on site_config;
create policy "site_config public read"
  on site_config for select
  using (true);

-- Writes are performed exclusively by the serverless function using the
-- service_role key, which bypasses RLS. No anon/authenticated write policy
-- is defined on purpose, so the anon/publishable key cannot modify config.
