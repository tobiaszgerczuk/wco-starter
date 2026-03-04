import CustomBehaviors from './components/CustomBehaviors.js';
import LazyLoader from './components/lazyload.js';
import Parallax from './components/parralax.js';
import Header from './modules/header.js';
import swipers from './modules/swipers.js';
import WcoShop from './ecommerce/woocommerce.js';

// import AOS from 'aos';
// import 'aos/dist/aos.css';
// Optional: uncomment when a block needs Swiper.
// import Swiper from 'swiper';
// import { Navigation, Pagination, Autoplay } from 'swiper/modules';
// import 'swiper/css';
// import 'swiper/css/navigation';
// import 'swiper/css/pagination';

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

  // If you install Swiper from npm, uncomment imports above and set the engine here.
  // swipers.setEngine(Swiper);
  //
  // Example registration:
  // swipers.register({
  //   name: 'testimonials',
  //   selector: '.js-swiper-testimonials',
  //   options: {
  //     modules: [Navigation, Pagination, Autoplay],
  //     slidesPerView: 1,
  //     spaceBetween: 24,
  //     navigation: {
  //       nextEl: '.swiper-button-next',
  //       prevEl: '.swiper-button-prev',
  //     },
  //     pagination: {
  //       el: '.swiper-pagination',
  //       clickable: true,
  //     },
  //   },
  // });
  //
  // You can also load Swiper globally and skip setEngine() if window.Swiper exists.
  swipers.init();
});
