(() => {
  const wp = window.wp || {};
  const blocks = wp.blocks || {};
  const blockEditor = wp.blockEditor || {};
  const element = wp.element || {};
  const components = wp.components || {};

  const registerBlockType = blocks.registerBlockType;
  const { InspectorControls, InnerBlocks, useBlockProps } = blockEditor;
  const { createElement: el } = element;
  const { PanelBody, SelectControl } = components;
  const isPolish = (document.documentElement.lang || '').toLowerCase().startsWith('pl');

  if (!registerBlockType || !useBlockProps || !InnerBlocks || !el || !PanelBody || !SelectControl) {
    return;
  }

  const LABELS = isPolish
    ? {
        title: 'Grupa kontenera',
        description: 'Blok opakowujący z zagnieżdżonymi blokami i wyborem szerokości kontenera.',
        panelTitle: 'Ustawienia kontenera',
        fieldLabel: 'Szerokość kontenera',
        default: 'Domyślna',
        wide: 'Szeroka',
        medium: 'Średnia',
        narrow: 'Wąska',
        full: 'Pełna szerokość',
      }
    : {
        title: 'Container Group',
        description: 'Wrapper block with nested blocks and configurable container width.',
        panelTitle: 'Container settings',
        fieldLabel: 'Container width',
        default: 'Default',
        wide: 'Wide',
        medium: 'Medium',
        narrow: 'Narrow',
        full: 'Full',
      };

  const CONTAINER_WIDTH_OPTIONS = [
    { label: LABELS.default, value: 'default' },
    { label: LABELS.wide, value: 'wide' },
    { label: LABELS.medium, value: 'medium' },
    { label: LABELS.narrow, value: 'narrow' },
    { label: LABELS.full, value: 'full' },
  ];

  registerBlockType('acf/container-group', {
    title: LABELS.title,
    description: LABELS.description,
    category: 'wco-blocks',
    icon: 'align-wide',
    supports: {
      align: ['full', 'wide'],
    },
    attributes: {
      containerWidth: {
        type: 'string',
        default: 'default',
      },
    },
    edit({ attributes, setAttributes }) {
      const { containerWidth } = attributes;
      const blockProps = useBlockProps({ className: 'block-container-group' });

      return el(
        'div',
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: LABELS.panelTitle,
              initialOpen: true,
            },
            el(SelectControl, {
              label: LABELS.fieldLabel,
              value: containerWidth,
              options: CONTAINER_WIDTH_OPTIONS,
              onChange(value) {
                setAttributes({ containerWidth: value });
              },
            })
          )
        ),
        el('div', blockProps, el(InnerBlocks, { templateLock: false }))
      );
    },
    save() {
      return el(InnerBlocks.Content);
    },
  });
})();
