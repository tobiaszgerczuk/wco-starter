export default class TwoColumnsColumn {
  static selector = '.block-two-columns__column';

  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    // Structural block, no frontend behavior by default.
  }
}
