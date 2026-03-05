#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const themeDir = process.cwd();
const variablesPath = path.join(themeDir, 'assets/scss/base/_variables.scss');
const outputPath = path.join(themeDir, 'theme.json');

const variablesContent = fs.readFileSync(variablesPath, 'utf8');

const variables = Object.fromEntries(
  Array.from(variablesContent.matchAll(/^\$([a-z0-9-]+):\s*(.+);$/gim)).map((match) => [
    match[1],
    match[2].trim(),
  ])
);

const required = [
  'container-default',
  'container-wide',
  'color-text',
  'color-bg',
  'color-surface',
  'color-surface-strong',
  'color-border',
  'color-muted',
  'color-primary',
  'color-primary-hover',
  'color-secondary',
  'color-accent',
  'color-black',
  'color-white',
  'section-space-none',
  'section-space-xs',
  'section-space-sm',
  'section-space-md',
  'section-space-lg',
  'section-space-xl',
  'font-family-base',
  'font-family-heading',
  'font-family-accent',
];

const missing = required.filter((key) => !variables[key]);
if (missing.length > 0) {
  console.error(`Missing variables in _variables.scss: ${missing.join(', ')}`);
  process.exit(1);
}

const colorMap = [
  ['text', 'Text', 'color-text'],
  ['bg', 'Background', 'color-bg'],
  ['surface', 'Surface', 'color-surface'],
  ['surface-strong', 'Surface Strong', 'color-surface-strong'],
  ['border', 'Border', 'color-border'],
  ['muted', 'Muted', 'color-muted'],
  ['primary', 'Primary', 'color-primary'],
  ['primary-hover', 'Primary Hover', 'color-primary-hover'],
  ['secondary', 'Secondary', 'color-secondary'],
  ['accent', 'Accent', 'color-accent'],
  ['black', 'Black', 'color-black'],
  ['white', 'White', 'color-white'],
];

const spacingMap = [
  ['none', 'None', 'section-space-none'],
  ['xs', 'XS', 'section-space-xs'],
  ['sm', 'SM', 'section-space-sm'],
  ['md', 'MD', 'section-space-md'],
  ['lg', 'LG', 'section-space-lg'],
  ['xl', 'XL', 'section-space-xl'],
];

const fontMap = [
  ['base', 'Base', 'font-family-base'],
  ['heading', 'Heading', 'font-family-heading'],
  ['accent', 'Accent', 'font-family-accent'],
];

const themeJson = {
  $schema: 'https://schemas.wp.org/trunk/theme.json',
  version: 3,
  settings: {
    appearanceTools: true,
    layout: {
      contentSize: variables['container-default'],
      wideSize: variables['container-wide'],
    },
    color: {
      defaultPalette: false,
      palette: colorMap.map(([slug, name, key]) => ({
        slug,
        name,
        color: variables[key],
      })),
    },
    spacing: {
      units: ['px', 'rem', '%', 'vw'],
      spacingSizes: spacingMap.map(([slug, name, key]) => ({
        slug,
        name,
        size: variables[key],
      })),
    },
    typography: {
      customFontSize: true,
      dropCap: false,
      lineHeight: true,
      fontFamilies: fontMap.map(([slug, name, key]) => ({
        slug,
        name,
        fontFamily: variables[key],
      })),
      fontSizes: [
        { slug: 'sm', name: 'Small', size: '0.875rem' },
        { slug: 'md', name: 'Medium', size: '1rem' },
        { slug: 'lg', name: 'Large', size: '1.25rem' },
        { slug: 'xl', name: 'XL', size: '2rem' },
      ],
    },
  },
  styles: {
    color: {
      text: 'var(--wp--preset--color--text)',
      background: 'var(--wp--preset--color--bg)',
    },
    typography: {
      fontFamily: 'var(--wp--preset--font-family--base)',
      lineHeight: '1.6',
    },
    elements: {
      heading: {
        typography: {
          fontFamily: 'var(--wp--preset--font-family--heading)',
          lineHeight: '1.2',
        },
      },
      link: {
        color: {
          text: 'var(--wp--preset--color--primary)',
        },
      },
      button: {
        color: {
          background: 'var(--wp--preset--color--primary)',
          text: 'var(--wp--preset--color--white)',
        },
        border: {
          radius: '10px',
        },
      },
    },
    spacing: {
      blockGap: variables['section-space-xs'],
    },
  },
};

fs.writeFileSync(outputPath, `${JSON.stringify(themeJson, null, 2)}\n`, 'utf8');
console.log('Generated theme.json from assets/scss/base/_variables.scss');
