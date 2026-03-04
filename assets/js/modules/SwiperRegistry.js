export default class SwiperRegistry {
  constructor(SwiperClass = window.Swiper || null) {
    this.SwiperClass = SwiperClass;
    this.definitions = [];
  }

  setEngine(SwiperClass) {
    this.SwiperClass = SwiperClass;
    return this;
  }

  register(definition) {
    if (!definition || !definition.selector) {
      return this;
    }

    this.definitions.push({
      name: definition.name || definition.selector,
      selector: definition.selector,
      options: definition.options || {},
    });

    return this;
  }

  init() {
    if (!this.SwiperClass || !this.definitions.length) {
      return;
    }

    this.definitions.forEach((definition) => {
      document.querySelectorAll(definition.selector).forEach((element, index) => {
        if (element.dataset.swiperInitialized === 'true') {
          return;
        }

        const options = typeof definition.options === 'function'
          ? definition.options(element, index)
          : definition.options;

        element.dataset.swiperInitialized = 'true';
        new this.SwiperClass(element, options);
      });
    });
  }
}
