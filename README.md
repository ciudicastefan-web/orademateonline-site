# orademateonline.ro — site

Site static **Astro** pentru Ora de Mate Online (meditații de matematică, grupe mici).

## Fluxul de lucru

1. Modificări locale în `src/` → `npm run dev` pentru preview live.
2. `npm run build` → generează `dist/` (care SE versionează — serverul nu are Node).
3. `git push` → pe server, cPanel Git™ Version Control trage modificările,
   iar `.cpanel.yml` copiază `dist/` în `public_html`.

## Structură

- `src/pages/` — paginile site-ului (`.astro`)
- `public/` — fișiere statice servite ca atare (favicon etc.)
- Fonturile sunt găzduite local prin `@fontsource` (GDPR: zero cereri către terți).

Acest repo conține DOAR cod. Documentele proiectului trăiesc în repo-ul privat local.
