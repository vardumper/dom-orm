import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  base: '/dom-orm/',
  title: "DOM-ORM",
  description: "Using a standardized XML tree structure to store data objects in a Doctrine-like ORM fashion.",
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: 'Get Started', link: '/get-started' },
      { text: 'Examples', link: '/usage-examples' }
    ],

    sidebar: [
      {
        text: 'Concept',
        items: [
          { text: 'Headless DB', link: '/features#headless-db' },
          { text: 'Concurrency', link: '/features#concurrency' },
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Versioning', link: '/features/versioning' },
          { text: 'Headless DB', link: '/features#headless-db' },
          { text: 'Concurrency', link: '/features#concurrency' },
          { text: 'XSLT & XPath', link: '/features#xslt-xpath' },
        ]
      },
      {
        text: 'Performance',
        items: [
          { text: 'No overhead', link: '/performance#no-overhead' },
          { text: 'Hash Maps & Cache', link: '/performance/#hash-maps-and-query-cache' },
          { text: 'Batch Inserts', link: '/performance#batch-inserts' },
        ]
      }

    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/vardumper/dom-orm' }
    ]
  }
})
