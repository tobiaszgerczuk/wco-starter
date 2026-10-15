/**
 * Editor side of the native (non-ACF) blocks. The block list, attributes, inspector controls and
 * default InnerBlocks template come from PHP (NativeBlocks::editor_config() -> window.wcoNativeBlocks).
 * Blocks with a custom editor DOM add a frame in FRAMES; the rest use the plain section frame.
 */
(() => {
  const { registerBlockType } = window.wp.blocks;
  const { InspectorControls, InnerBlocks, useBlockProps, MediaUpload, MediaUploadCheck } = window.wp.blockEditor;
  const { PanelBody, SelectControl, TextControl, RangeControl, Button, __experimentalHStack: HStack } = window.wp.components;
  const { useSelect } = window.wp.data;
  const { createElement: el } = window.wp.element;

  const configs = window.wcoNativeBlocks || [];
  if (!configs.length) {
    return;
  }

  const isPolish = (document.documentElement.lang || '').toLowerCase().startsWith('pl');
  const t = (pl, en) => (isPolish ? pl : en);

  const SCALE = ['none', 'xs', 'sm', 'md', 'lg', 'xl'];
  const toOptions = (map) => Object.entries(map).map(([value, label]) => ({ value, label }));
  const SCALE_OPTIONS = SCALE.map((value) => ({ value, label: value === 'none' ? t('Brak', 'None') : value.toUpperCase() }));
  const WIDTH_OPTIONS = toOptions({
    default: t('Domyślna', 'Default'),
    wide: t('Szeroka', 'Wide'),
    medium: t('Średnia', 'Medium'),
    narrow: t('Wąska', 'Narrow'),
    full: t('Pełna szerokość', 'Full width'),
  });
  const CONTAINER_CLASSES = { default: 'container', wide: 'container-wide', medium: 'container-medium', narrow: 'container-narrow', full: '' };

  /** Mirrors SectionSettings::build_classes() / container_class() / inline_style(). */
  function sectionProps(slug, attributes) {
    const classes = [`block-${slug}`, 'section'];
    const background = /^#([a-f0-9]{3}|[a-f0-9]{6})$/i.test(attributes.section_background_color || '') ? attributes.section_background_color : null;
    if (background) {
      classes.push('section-bg');
    }
    [
      ['section_gap_top', 'section-gap-top-'],
      ['section_gap_bottom', 'section-gap-bottom-'],
      ['section_space_top', 'section-space-top-'],
      ['section_space_bottom', 'section-space-bottom-'],
    ].forEach(([attr, prefix]) => {
      if (SCALE.includes(attributes[attr])) {
        classes.push(prefix + attributes[attr]);
      }
    });

    return {
      className: classes.join(' '),
      style: background ? { '--section-background-color': background } : undefined,
      containerClass: CONTAINER_CLASSES[attributes.container_width] ?? 'container',
    };
  }

  /** Content wrapper shared with the front end (Templating::inner_blocks()), so layout CSS matches. */
  const innerBlocks = (template) =>
    el('div', { className: 'acf-innerblocks-container' }, el(InnerBlocks, { template, templateLock: false }));

  const FRAMES = {
    hero({ attributes, containerClass, template }) {
      const imageId = attributes.hero_background_image;
      const media = useSelect((select) => (imageId ? select('core').getMedia(imageId) : null), [imageId]);
      const overlay = (attributes.hero_overlay ?? 40) / 100;

      return el(
        'div',
        { className: containerClass || undefined },
        imageId && media
          ? el(
              'div',
              { className: 'block-hero__bg', 'aria-hidden': 'true' },
              el('img', { className: 'block-hero__bg-image', src: media.source_url, alt: '' }),
              el('span', { className: 'block-hero__overlay', style: { opacity: overlay } })
            )
          : null,
        el(
          'div',
          { className: `block-hero__inner block-hero__inner--image-${attributes.hero_image_position || 'right'}` },
          innerBlocks(template)
        )
      );
    },
  };

  const FRAME_EXTRA_CLASSES = {
    hero: (attributes) => [`block-hero--h-${attributes.hero_min_height || 'auto'}`, attributes.hero_background_image ? 'block-hero--has-bg' : ''],
  };

  function ImageControl({ label, value, onChange }) {
    const media = useSelect((select) => (value ? select('core').getMedia(value) : null), [value]);

    return el(
      'div',
      { style: { marginBottom: '16px' } },
      el('p', { style: { margin: '0 0 8px', fontWeight: 600 } }, label),
      media ? el('img', { src: media.source_url, alt: '', style: { display: 'block', maxWidth: '100%', marginBottom: '8px' } }) : null,
      el(
        MediaUploadCheck,
        {},
        el(MediaUpload, {
          allowedTypes: ['image'],
          value,
          onSelect: (item) => onChange(item.id),
          render: ({ open }) =>
            el(
              HStack,
              { justify: 'flex-start' },
              el(Button, { variant: 'secondary', onClick: open }, value ? t('Zmień obraz', 'Replace image') : t('Wybierz obraz', 'Choose image')),
              value ? el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => onChange(undefined) }, t('Usuń', 'Remove')) : null
            ),
        })
      )
    );
  }

  function renderControl(control, attributes, setAttributes) {
    const value = attributes[control.attr];
    const onChange = (next) => setAttributes({ [control.attr]: next });
    const common = { key: control.attr, label: control.label, __nextHasNoMarginBottom: true };

    switch (control.type) {
      case 'select':
        return el(SelectControl, { ...common, value, options: toOptions(control.options || {}), onChange });
      case 'range':
        return el(RangeControl, { ...common, value: value ?? control.min ?? 0, min: control.min ?? 0, max: control.max ?? 100, onChange });
      case 'image':
        return el(ImageControl, { key: control.attr, label: control.label, value, onChange });
      default:
        return el(TextControl, { ...common, value: value || '', onChange });
    }
  }

  configs.forEach((config) => {
    const slug = config.slug;
    const frame = FRAMES[slug];

    registerBlockType(config.name, {
      title: config.title,
      description: config.description,
      category: config.category,
      icon: config.icon,
      keywords: config.keywords,
      supports: config.supports,
      attributes: config.attributes,
      edit({ attributes, setAttributes }) {
        const section = sectionProps(slug, attributes);
        const extra = FRAME_EXTRA_CLASSES[slug] ? FRAME_EXTRA_CLASSES[slug](attributes).filter(Boolean) : [];
        const blockProps = useBlockProps({
          className: [section.className, ...extra].join(' '),
          style: section.style,
        });
        const visible = (control) => !control.showWhen || attributes[control.showWhen];

        return el(
          'section',
          blockProps,
          el(
            InspectorControls,
            {},
            config.controls.length
              ? el(PanelBody, { title: config.panel, initialOpen: true }, config.controls.filter(visible).map((c) => renderControl(c, attributes, setAttributes)))
              : null,
            config.sectionSettings
              ? el(
                  PanelBody,
                  { title: t('Ustawienia sekcji', 'Section settings'), initialOpen: false },
                  el(TextControl, {
                    label: t('Kolor tła', 'Background color'),
                    help: t('Kolor w formacie #rrggbb. Puste = brak tła.', 'Hex color like #rrggbb. Empty = no background.'),
                    value: attributes.section_background_color || '',
                    onChange: (value) => setAttributes({ section_background_color: value }),
                  }),
                  el(SelectControl, {
                    label: t('Szerokość treści', 'Content width'),
                    value: attributes.container_width,
                    options: WIDTH_OPTIONS,
                    onChange: (value) => setAttributes({ container_width: value }),
                  }),
                  el(TextControl, {
                    label: t('ID bloku (kotwica)', 'Block ID (anchor)'),
                    value: attributes.block_id || '',
                    onChange: (value) => setAttributes({ block_id: value }),
                  })
                )
              : null,
            config.sectionSettings
              ? el(
                  PanelBody,
                  { title: t('Odstępy', 'Spacing'), initialOpen: false },
                  [
                    ['section_gap_top', t('Odstęp zewnętrzny góra', 'Gap top')],
                    ['section_gap_bottom', t('Odstęp zewnętrzny dół', 'Gap bottom')],
                    ['section_space_top', t('Odstęp wewnętrzny góra', 'Space top')],
                    ['section_space_bottom', t('Odstęp wewnętrzny dół', 'Space bottom')],
                  ].map(([attr, label]) =>
                    el(SelectControl, {
                      key: attr,
                      label,
                      value: attributes[attr],
                      options: SCALE_OPTIONS,
                      onChange: (value) => setAttributes({ [attr]: value }),
                    })
                  )
                )
              : null
          ),
          frame
            ? frame({ attributes, containerClass: section.containerClass, template: config.template })
            : el('div', { className: section.containerClass || undefined }, innerBlocks(config.template))
        );
      },
      save: () => el(InnerBlocks.Content),
    });
  });
})();
