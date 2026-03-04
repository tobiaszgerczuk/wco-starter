import CustomBehaviors from './components/CustomBehaviors.js';
import LazyLoader from './components/lazyload.js';
import Parallax from './components/parralax.js';
import Header from './modules/header.js';
import WcoShop from './ecommerce/woocommerce.js';

// import AOS from 'aos';
// import 'aos/dist/aos.css';

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
  new Header();
  new LazyLoader();
  new CustomBehaviors();
  new Parallax('[data-parallax]', 0.4);
  new WcoShop();
  initBlockModules();

  // AOS.init({
  //   duration: 800,
  //   once: true,
  // });
});
