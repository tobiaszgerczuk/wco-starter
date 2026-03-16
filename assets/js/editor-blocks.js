import './container-group-editor.js';
import './two-columns-editor.js';
import swipers from './modules/swipers.js';

function initBlockModules() {
  const context = require.context('../../views/blocks', true, /\/[^/]+\.js$/);

  context.keys().forEach((key) => {
    const BlockModule = context(key).default;
    if (!BlockModule || !BlockModule.selector) {
      return;
    }

    document.querySelectorAll(BlockModule.selector).forEach((element) => {
      if (element.dataset.blockInitialized === 'true') {
        return;
      }

      element.dataset.blockInitialized = 'true';
      new BlockModule(element);
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initBlockModules();
  swipers.init();
});
