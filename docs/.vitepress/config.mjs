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
      { text: 'Frameworks', link: '/frameworks/symfony' },
      { text: 'Examples', link: '/usage-examples' }
    ],

    sidebar: [
      { text: 'Get started', link: '/get-started' },
      {
        text: 'Relationships',
        items: [
          { text: 'One-to-One', link: '/relationships#one-to-one' },
          { text: 'One-to-Many', link: '/relationships#one-to-many' },
          { text: 'Many-to-One', link: '/relationships#many-to-one' },
          { text: 'Many-to-Many', link: '/relationships#many-to-many' },
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Versioning', link: '/features/versioning' },
          { text: 'Schema Evolution', link: '/features/schema-evolution' },
          { text: 'Headless DB', link: '/features/headless-db' },
          { text: 'Encryption', link: '/features/encryption' },
          { text: 'Concurrency', link: '/features/concurrency' },
          { text: 'XSLT & XPath', link: '/get-started#xslt' },
          { text: 'Unit Tests', link: '/features/tests' },
        ]
      },
      {
        text: 'Performance',
        items: [
          { text: 'No overhead', link: '/performance#no-overhead' },
          { text: 'Hash Maps & Cache', link: '/performance/#hash-maps-and-query-cache' },
          { text: 'Batch Inserts', link: '/performance#batch-inserts' },
        ]
      },
      {
        text: 'Frameworks',
        items: [
          { text: 'Symfony', link: '/frameworks/symfony' },
          { text: 'Laravel', link: '/frameworks/laravel' },
          { text: 'CakePHP', link: '/frameworks/cakephp' },
          { text: 'CodeIgniter 4', link: '/frameworks/codeigniter' },
          { text: 'Yii', link: '/frameworks/yii' },
          { text: 'Phalcon', link: '/frameworks/phalcon' },
          { text: 'Leaf PHP', link: '/frameworks/leaf' },
          { text: 'Slim', link: '/frameworks/slim' },
          { text: 'Mezzio', link: '/frameworks/mezzio' },
          { text: 'Laminas', link: '/frameworks/laminas' },
        ]
      }

    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/vardumper/dom-orm' }
    ]
  }
})
