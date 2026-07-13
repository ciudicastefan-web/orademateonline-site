// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://orademateonline.ro',
  trailingSlash: 'ignore',
  // paginile se descarcă în fundal când utilizatorul trece cu mouse-ul peste
  // un link — click-ul găsește pagina deja adusă, navigarea pare instantanee
  prefetch: {
    prefetchAll: true,
    defaultStrategy: 'hover',
  },
  integrations: [
    sitemap({
      // paginile de autentificare nu au ce căuta în rezultatele Google
      filter: (page) =>
        !page.includes('/autentificare') && !page.includes('/inregistrare') && !page.includes('/resetare'),
    }),
  ],
});
