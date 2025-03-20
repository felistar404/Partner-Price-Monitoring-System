import { defineConfig } from 'astro/config';

export default defineConfig({
  srcDir: './src',
  outDir: './dist',
  server: {
    port: 3000,
    host: true 
  },
  vite: {
    resolve: {
      alias: {
        '@components': '/src/components',
        '@layouts': '/src/layouts',
        '@assets': '/assets'
      }
    },
    define: {
      'import.meta.env.HK_PUBLIC_API_BASE': 
        JSON.stringify(process.env.HK_PUBLIC_API_BASE || 'http://localhost/monitoring_system/backend/public/hk/api')
    }
  },
  i18n: {
    locales: ["en", "zh-hant", "zh-hans"],
    defaultLocale: "en",
    routing: {
      prefixDefaultLocale: false // Makes /en/ URLs redirect to /
    }
  }
});