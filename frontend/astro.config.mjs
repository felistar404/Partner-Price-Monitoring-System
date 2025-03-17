import { defineConfig } from 'astro/config';

export default defineConfig({
  // Base directory where your Astro files live
  srcDir: './src',
  
  // Output directory for the build
  outDir: './dist',
  
  // Configure dev server
  server: {
    port: 3000,
    host: true // Listen on all addresses, including network
  },
  
  // Configure aliases for imports
  vite: {
    resolve: {
      alias: {
        '@components': '/src/components',
        '@layouts': '/src/layouts',
        '@assets': '/assets'
      }
    }
  }
});