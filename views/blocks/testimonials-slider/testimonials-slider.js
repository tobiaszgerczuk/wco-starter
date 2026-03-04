import swipers from '../../../assets/js/modules/swipers.js';

export default class TestimonialsSlider {
  static selector = '.block-testimonials-slider';
  static registered = false;

  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    if (!TestimonialsSlider.registered) {
      swipers.register({
        name: 'testimonials-slider',
        selector: '.js-swiper-testimonials',
        options: (element) => ({
          slidesPerView: 1,
          spaceBetween: 24,
          autoHeight: true,
          pagination: {
            el: element.querySelector('.swiper-pagination'),
            clickable: true,
          },
          navigation: {
            nextEl: element.querySelector('.swiper-button-next'),
            prevEl: element.querySelector('.swiper-button-prev'),
          },
          breakpoints: {
            768: {
              slidesPerView: 2,
            },
          },
        }),
      });

      TestimonialsSlider.registered = true;
    }
  }
}
