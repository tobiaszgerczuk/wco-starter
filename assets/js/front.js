import CustomBehaviors from './components/CustomBehaviors.js';
import LazyLoader from './components/lazyload.js';
import Parallax from './components/parralax.js';
import Header from './modules/header.js';

// import AOS from 'aos';
// import 'aos/dist/aos.css';

document.addEventListener('DOMContentLoaded', () => {
  new Header();
  new LazyLoader();
  new CustomBehaviors();
  new LandsTableSorter("#landsTable");
  new WcoGallery('.wcogallery', '#wcogallery-lightbox');
  new Parallax('[data-parallax]', 0.4);

  // AOS.init({
  //   duration: 800,
  //   once: true,
  // });
});
