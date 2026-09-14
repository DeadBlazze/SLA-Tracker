import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import strip from '@rollup/plugin-strip'

export default defineConfig({
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    'process.env': {},
    'process': { env: {} }
  },
  plugins: [
    vue(),
    strip({
      functions: ['console.*', 'assert.*'],
      include: ['**/*.(js|ts|vue)'],
    }),
    {
      name: 'remove-console-create-task',
      renderChunk(code) {
        return {
          code: code.replace(
            /typeof\s+console\.createTask\s*!==\s*["']undefined["']\s*\?\s*console\.createTask\s*:\s*_createTask/g,
            '_createTask'
          ),
          map: null,
        }
      },
    },
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    // target задается именно здесь
    target: 'es2015',
    minify: false,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('./src/main.js', import.meta.url)),
      name: 'MyVueWidget',
      formats: ['iife'],
      fileName: () => 'app.js',
    },
    rollupOptions: {
      output: {
        banner: '(function() {\n',
        footer: '\n})();',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'style.css'
          }
          return '[name].[ext]'
        },
      },
    },
  },
})