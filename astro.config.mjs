// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://orademateonline.ro',
  trailingSlash: 'ignore',
  integrations: [
    sitemap({
      // paginile de autentificare nu au ce căuta în rezultatele Google
      filter: (page) =>
        !page.includes('/autentificare') && !page.includes('/inregistrare') && !page.includes('/resetare'),
    }),
  ],
});
