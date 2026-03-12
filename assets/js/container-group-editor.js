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

  if (!registerBlockType || !useBlockProps || !InnerBlocks || !el || !PanelBody || !SelectControl) {
    return;
  }

  const LABELS = {
    title: 'Container Group',
    description: 'Wrapper block with nested blocks and configurable container width.',
  };

  const CONTAINER_WIDTH_OPTIONS = [
    { label: 'Default', value: 'default' },
    { label: 'Wide', value: 'wide' },
    { label: 'Medium', value: 'medium' },
    { label: 'Narrow', value: 'narrow' },
    { label: 'Full', value: 'full' },
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
              title: 'Container settings',
              initialOpen: true,
            },
            el(SelectControl, {
              label: 'Container width',
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
