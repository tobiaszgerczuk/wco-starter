(() => {
  const wp = window.wp || {};
  const blocks = wp.blocks || {};
  const blockEditor = wp.blockEditor || {};
  const data = wp.data || {};
  const element = wp.element || {};
  const components = wp.components || {};

  const registerBlockType = blocks.registerBlockType;
  const { InspectorControls, InnerBlocks, useBlockProps, useInnerBlocksProps } = blockEditor;
  const { useSelect } = data;
  const { createElement: el } = element;
  const { PanelBody, SelectControl } = components;
  const isPolish = (document.documentElement.lang || '').toLowerCase().startsWith('pl');

  if (!registerBlockType || !useBlockProps || !useInnerBlocksProps || !InnerBlocks || !useSelect || !el || !PanelBody || !SelectControl) {
    return;
  }

  const containerWidthOptions = [
    { label: 'Default', value: 'default' },
    { label: 'Wide', value: 'wide' },
    { label: 'Medium', value: 'medium' },
    { label: 'Narrow', value: 'narrow' },
    { label: 'Full', value: 'full' },
  ];

  const columnsRatioOptions = [
    { label: '50 / 50', value: '50-50' },
    { label: '60 / 40', value: '60-40' },
    { label: '40 / 60', value: '40-60' },
    { label: '70 / 30', value: '70-30' },
    { label: '30 / 70', value: '30-70' },
  ];

  const columnTemplate = [
    [
      'acf/two-columns-column',
      {
        columnPosition: 'left',
      },
      [],
    ],
    [
      'acf/two-columns-column',
      {
        columnPosition: 'right',
      },
      [],
    ],
  ];

  const labels = isPolish
    ? {
        blockTitle: 'Dwie kolumny',
        blockDescription: 'Blok układu dwóch kolumn z wyborem szerokości kontenera i proporcji.',
        panelTitle: 'Ustawienia układu',
        containerWidth: 'Szerokość kontenera',
        columnsRatio: 'Proporcje kolumn',
        columnTitle: 'Kolumna dwóch kolumn',
        columnDescription: 'Wewnętrzna kolumna dla bloku dwóch kolumn.',
        default: 'Domyślna',
        wide: 'Szeroka',
        medium: 'Średnia',
        narrow: 'Wąska',
        full: 'Pełna szerokość',
      }
    : {
        blockTitle: 'Two Columns',
        blockDescription: 'Two-column layout block with container width and ratio controls.',
        panelTitle: 'Layout settings',
        containerWidth: 'Container width',
        columnsRatio: 'Columns ratio',
        columnTitle: 'Two Columns Column',
        columnDescription: 'Inner column for the Two Columns layout block.',
        default: 'Default',
        wide: 'Wide',
        medium: 'Medium',
        narrow: 'Narrow',
        full: 'Full',
      };

  containerWidthOptions.forEach((option) => {
    option.label = labels[option.value];
  });

  registerBlockType('acf/two-columns', {
    title: labels.blockTitle,
    description: labels.blockDescription,
    category: 'wco-blocks',
    icon: 'columns',
    supports: {
      align: ['full', 'wide'],
    },
    attributes: {
      containerWidth: {
        type: 'string',
        default: 'default',
      },
      columnsRatio: {
        type: 'string',
        default: '50-50',
      },
    },
    edit({ attributes, setAttributes, clientId }) {
      const { containerWidth, columnsRatio } = attributes;
      const blockProps = useBlockProps({ className: 'block-two-columns' });
      const innerBlockCount = useSelect((select) => {
        const block = select('core/block-editor').getBlock(clientId);
        return block?.innerBlocks?.length || 0;
      }, [clientId]);
      const gridInnerBlocksProps = useInnerBlocksProps(
        {
          className: `block-two-columns__grid block-two-columns__grid--ratio-${columnsRatio}`,
        },
        {
          allowedBlocks: ['acf/two-columns-column'],
          template: columnTemplate,
          templateInsertUpdatesSelection: true,
          renderAppender: innerBlockCount >= 2 ? false : InnerBlocks.ButtonBlockAppender,
        }
      );

      return el(
        'div',
        blockProps,
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: labels.panelTitle,
              initialOpen: true,
            },
            el(SelectControl, {
              label: labels.containerWidth,
              value: containerWidth,
              options: containerWidthOptions,
              onChange(value) {
                setAttributes({ containerWidth: value });
              },
            }),
            el(SelectControl, {
              label: labels.columnsRatio,
              value: columnsRatio,
              options: columnsRatioOptions,
              onChange(value) {
                setAttributes({ columnsRatio: value });
              },
            })
          )
        ),
        el('div', gridInnerBlocksProps)
      );
    },
    save() {
      return el(InnerBlocks.Content);
    },
  });

  registerBlockType('acf/two-columns-column', {
    title: labels.columnTitle,
    description: labels.columnDescription,
    category: 'wco-blocks',
    icon: 'excerpt-view',
    parent: ['acf/two-columns'],
    supports: {
      html: false,
      reusable: false,
    },
    attributes: {
      columnPosition: {
        type: 'string',
        default: 'left',
      },
    },
    edit({ attributes }) {
      const position = attributes.columnPosition === 'right' ? 'right' : 'left';
      const blockProps = useBlockProps({
        className: `block-two-columns__column block-two-columns__column--${position}`,
      });
      const columnInnerBlocksProps = useInnerBlocksProps(blockProps, {
        templateLock: false,
        renderAppender: InnerBlocks.ButtonBlockAppender,
      });

      return el('div', columnInnerBlocksProps);
    },
    save({ attributes }) {
      const position = attributes.columnPosition === 'right' ? 'right' : 'left';
      return el(
        'div',
        {
          className: `block-two-columns__column block-two-columns__column--${position}`,
        },
        el(InnerBlocks.Content)
      );
    },
  });
})();
