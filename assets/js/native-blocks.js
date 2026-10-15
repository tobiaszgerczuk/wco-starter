/**
 * Editor side of the native (non-ACF) blocks. The block list, attributes, inspector controls and
 * default InnerBlocks template come from PHP (NativeBlocks::editor_config() -> window.wcoNativeBlocks).
 * Blocks with a custom editor DOM add a frame in FRAMES; the rest use the plain section frame.
 */
(() => {
  const { registerBlockType } = window.wp.blocks;
  const { InspectorControls, InnerBlocks, RichText, useBlockProps, MediaUpload, MediaUploadCheck } = window.wp.blockEditor;
  const { PanelBody, SelectControl, TextControl, RangeControl, ToggleControl, Button, __experimentalHStack: HStack } = window.wp.components;
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
  const innerBlocks = (template, allowedBlocks) =>
    el('div', { className: 'acf-innerblocks-container' }, el(InnerBlocks, { template, allowedBlocks: allowedBlocks && allowedBlocks.length ? allowedBlocks : undefined, templateLock: false }));

  /**
   * Fills a class template from block.json (wco.frame): `{attr:default}` prints the attribute,
   * `{attr? extra-class}` prints the extra class when the attribute is truthy.
   */
  function fillClasses(pattern, attributes) {
    return String(pattern || '')
      .replace(/\{(\w+)\?([^}]*)\}/g, (_, attr, classes) => (attributes[attr] ? classes : ''))
      .replace(/\{(\w+)(?::([^}]*))?\}/g, (_, attr, fallback) => {
        const value = attributes[attr];
        return value === undefined || value === null || value === '' ? fallback || '' : String(value);
      });
  }

  const SPACER_SCALE = { none: '0px', xs: 'var(--section-space-xs)', sm: 'var(--section-space-sm)', md: 'var(--section-space-md)', lg: 'var(--section-space-lg)', xl: 'var(--section-space-xl)' };

  function spacerHeight(attributes, device) {
    const custom = Number(attributes[`spacer_${device}_custom`] || 0);
    if (attributes[`spacer_${device}_mode`] === 'custom' && custom > 0) {
      return `${custom}px`;
    }
    return SPACER_SCALE[attributes[`spacer_${device}_size`]] || SPACER_SCALE.md;
  }

  /** Generic editor DOM for blocks described by wco.frame; mirrors the block's Twig structure. */
  function genericFrame({ config, attributes, containerClass, template, allowedBlocks }) {
    const frame = config.frame || {};
    const holder = (children) => el('div', { className: 'acf-innerblocks-container' }, children);
    const blocks = el(InnerBlocks, { template, allowedBlocks: allowedBlocks.length ? allowedBlocks : undefined, templateLock: false });

    if (frame.leaf) {
      return el('div', { className: containerClass || undefined }, el(frame.leaf.tag || 'div', { className: fillClasses(frame.leaf.class, attributes) }));
    }

    const content = [];
    content.push(frame.head ? el('div', { key: 'head', className: frame.head }, holder(blocks)) : el('div', { key: 'blocks' }, holder(blocks)));
    if (frame.after) {
      content.push(el('div', { key: 'after', className: fillClasses(frame.after.class, attributes) }, el('p', { style: { margin: 0, opacity: 0.7 } }, frame.after.text)));
    }

    const body = frame.inner ? el('div', { className: fillClasses(frame.inner, attributes) }, content) : content;
    return el('div', { className: containerClass || undefined }, body);
  }

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

  FRAMES['testimonials-slider'] = ({ attributes, setAttributes, containerClass }) => {
    const items = Array.isArray(attributes.testimonials_slider_items) ? attributes.testimonials_slider_items : [];
    const setItems = (next) => setAttributes({ testimonials_slider_items: next });
    const patch = (index, change) => setItems(items.map((item, i) => (i === index ? { ...item, ...change } : item)));
    const text = (tagName, className, value, placeholder, onChange, formats) =>
      el(RichText, { tagName, className, value: value || '', placeholder, allowedFormats: formats || [], onChange });

    return el(
      'div',
      { className: containerClass || 'container' },
      el(
        'div',
        { className: 'section-header block-testimonials-slider__header' },
        text('p', 'section-header__eyebrow', attributes.testimonials_slider_eyebrow, t('Nadtytuł', 'Eyebrow'), (v) => setAttributes({ testimonials_slider_eyebrow: v })),
        text('h2', 'section-header__title', attributes.testimonials_slider_title, t('Tytuł sekcji', 'Section title'), (v) => setAttributes({ testimonials_slider_title: v })),
        text('div', 'section-header__intro', attributes.testimonials_slider_intro, t('Krótki opis', 'Intro'), (v) => setAttributes({ testimonials_slider_intro: v }), ['core/bold', 'core/italic', 'core/link'])
      ),
      el(
        'div',
        { className: 'block-testimonials-slider__slider', style: { display: 'grid', gap: '24px' } },
        items.map((item, index) =>
          el(
            'article',
            { key: index, className: 'block-testimonials-slider__slide' },
            el(
              'div',
              { className: 'block-testimonials-slider__card' },
              text('blockquote', 'block-testimonials-slider__quote', item.quote, t('Treść opinii', 'Quote'), (v) => patch(index, { quote: v }), ['core/bold', 'core/italic']),
              el(
                'div',
                { className: 'block-testimonials-slider__meta' },
                text('strong', 'block-testimonials-slider__name', item.author_name, t('Imię i nazwisko', 'Name'), (v) => patch(index, { author_name: v })),
                text('span', 'block-testimonials-slider__role', item.author_role, t('Stanowisko / firma', 'Role'), (v) => patch(index, { author_role: v }))
              ),
              el(Button, { variant: 'link', isDestructive: true, onClick: () => setItems(items.filter((_, i) => i !== index)) }, t('Usuń opinię', 'Remove'))
            )
          )
        )
      ),
      el(Button, { variant: 'secondary', style: { marginTop: '16px' }, onClick: () => setItems([...items, { quote: '', author_name: '', author_role: '' }]) }, t('Dodaj opinię', 'Add testimonial'))
    );
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

  function CategoryControl({ label, value, onChange, taxonomy }) {
    const categories = useSelect((select) => select('core').getEntityRecords('taxonomy', taxonomy || 'category', { per_page: 100 }), [taxonomy]);
    const options = [{ value: 0, label: t('Wszystkie kategorie', 'All categories') }].concat((categories || []).map((c) => ({ value: c.id, label: c.name })));

    return el(SelectControl, { label, value: value || 0, options, __nextHasNoMarginBottom: true, onChange: (next) => onChange(Number(next)) });
  }

  function renderControl(control, attributes, setAttributes) {
    const value = attributes[control.attr];
    const onChange = (next) => setAttributes({ [control.attr]: next });
    const common = { key: control.attr, label: control.label, __nextHasNoMarginBottom: true };

    switch (control.type) {
      case 'select':
        return el(SelectControl, { ...common, value, options: toOptions(control.options || {}), onChange });
      case 'toggle':
        return el(ToggleControl, { key: control.attr, label: control.label, checked: !!value, __nextHasNoMarginBottom: true, onChange });
      case 'number':
        return el(TextControl, { ...common, type: 'number', min: control.min, max: control.max, value: value ?? '', onChange: (next) => onChange(next === '' ? 0 : Number(next)) });
      case 'category':
      case 'term':
        return el(CategoryControl, { key: control.attr, label: control.label, value, onChange, taxonomy: control.taxonomy });
      case 'link':
        return el(
          'div',
          { key: control.attr },
          el(TextControl, { label: control.label + ' (URL)', value: (value && value.url) || '', __nextHasNoMarginBottom: true, onChange: (url) => onChange(url ? { ...(value || {}), url } : undefined) }),
          el(TextControl, { label: control.label + ' (' + t('tekst', 'label') + ')', value: (value && value.title) || '', __nextHasNoMarginBottom: true, onChange: (title) => onChange({ ...(value || {}), title }) })
        );
      case 'range':
        return el(RangeControl, { ...common, value: value ?? control.min ?? 0, min: control.min ?? 0, max: control.max ?? 100, onChange });
      case 'image':
        return el(ImageControl, { key: control.attr, label: control.label, value, onChange });
      default:
        return el(TextControl, { ...common, value: value || '', onChange });
    }
  }

  function sectionPanels(attributes, setAttributes) {
    return [
      el(
        PanelBody,
        { key: 'section', title: t('Ustawienia sekcji', 'Section settings'), initialOpen: false },
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
      ),
      el(
        PanelBody,
        { key: 'spacing', title: t('Odstępy', 'Spacing'), initialOpen: false },
        [
          ['section_gap_top', t('Odstęp zewnętrzny góra', 'Gap top')],
          ['section_gap_bottom', t('Odstęp zewnętrzny dół', 'Gap bottom')],
          ['section_space_top', t('Odstęp wewnętrzny góra', 'Space top')],
          ['section_space_bottom', t('Odstęp wewnętrzny dół', 'Space bottom')],
        ].map(([attr, label]) =>
          el(SelectControl, { key: attr, label, value: attributes[attr], options: SCALE_OPTIONS, onChange: (value) => setAttributes({ [attr]: value }) })
        )
      ),
    ];
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
      parent: config.parent || undefined,
      edit({ attributes, setAttributes }) {
        const section = sectionProps(slug, attributes);
        const extra = FRAME_EXTRA_CLASSES[slug] ? FRAME_EXTRA_CLASSES[slug](attributes).filter(Boolean) : [];
        const frameConfig = config.frame || {};
        const isItem = !!frameConfig.item;
        const isFree = !!frameConfig.noSection;
        const blockProps = useBlockProps(
          isItem
            ? { className: fillClasses(frameConfig.item.class, attributes) }
            : isFree
              ? {
                  className: fillClasses(frameConfig.leaf.class, attributes),
                  style: { minHeight: spacerHeight(attributes, 'desktop'), textAlign: 'center', fontSize: '12px', background: 'repeating-linear-gradient(45deg, transparent, transparent 6px, rgba(0,0,0,.05) 6px, rgba(0,0,0,.05) 12px)' },
                }
              : { className: [section.className, ...extra].join(' '), style: section.style }
        );
        const visible = (control) => {
          const when = control.showWhen;
          if (!when) {
            return true;
          }
          if (typeof when === 'string') {
            return !!attributes[when];
          }
          const current = attributes[when.attr];
          return String(current === true ? 1 : current === false ? 0 : current) === String(when.value);
        };

        const inspector = el(
          InspectorControls,
          {},
          config.controls.length
            ? el(PanelBody, { title: config.panel, initialOpen: true }, config.controls.filter(visible).map((c) => renderControl(c, attributes, setAttributes)))
            : null,
          config.sectionSettings ? sectionPanels(attributes, setAttributes) : null
        );
        const template = config.template || [];
        const allowedBlocks = config.allowedBlocks || [];

        if (isItem) {
          const item = frameConfig.item;
          const badge = frameConfig.badge;

          return el(
            item.tag || 'div',
            blockProps,
            inspector,
            badge && attributes[badge.when]
              ? el('span', { className: badge.class }, attributes[badge.attr] || badge.default)
              : null,
            innerBlocks(template, allowedBlocks)
          );
        }

        if (isFree) {
          return el('div', blockProps, inspector, frameConfig.leaf.text + ': ' + spacerHeight(attributes, 'desktop') + ' / ' + spacerHeight(attributes, 'mobile'));
        }

        const sectionBody = frame
          ? frame({ attributes, setAttributes, containerClass: section.containerClass, template })
          : genericFrame({ config, attributes, containerClass: section.containerClass, template, allowedBlocks });

        return el('section', blockProps, inspector, sectionBody);
      },
      save: () => (config.frame && config.frame.leaf ? null : el(InnerBlocks.Content)),
    });
  });
})();
