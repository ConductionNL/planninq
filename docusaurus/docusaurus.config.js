// @ts-check
/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Planix',
  tagline: 'Flow-based kanban project and task management for Nextcloud dev and IT teams',
  url: 'https://planix.app',
  baseUrl: '/',
  organizationName: 'conductionnl',
  projectName: 'planix',
  trailingSlash: false,
  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',
  i18n: { defaultLocale: 'en', locales: ['en'] },
  markdown: { mermaid: true },
  themes: ['@docusaurus/theme-mermaid'],
  presets: [
    [
      'classic',
      ({
        docs: {
          path: '../docs',
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: 'https://github.com/conductionnl/planix/tree/main/docusaurus/',
        },
        blog: false,
        theme: { customCss: require.resolve('./src/css/custom.css') },
      }),
    ],
  ],
  themeConfig: ({
    mermaid: { theme: { light: 'default', dark: 'dark' } },
    navbar: {
      title: 'Planix',
      logo: { alt: 'Planix Logo', src: 'img/logo.svg' },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'tutorialSidebar',
          position: 'left',
          label: 'Documentation',
        },
        {
          href: 'https://github.com/conductionnl/planix',
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Docs',
          items: [{ label: 'Documentation', to: '/docs' }],
        },
        {
          title: 'Community',
          items: [{ label: 'GitHub', href: 'https://github.com/conductionnl/planix' }],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} <a href="https://conduction.nl">Conduction B.V.</a>`,
    },
    prism: {
      theme: require('prism-react-renderer/themes/github'),
      darkTheme: require('prism-react-renderer/themes/dracula'),
    },
  }),
};
module.exports = config;
