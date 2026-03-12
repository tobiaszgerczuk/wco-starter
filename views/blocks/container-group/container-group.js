export default class ContainerGroup {
  static selector = '.block-container-group';

  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    // Group blocks are purely structural, no extra JS by default.
  }
}
