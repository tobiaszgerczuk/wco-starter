// Loads the map iframe only after a click, so no third-party request is made before.
const ALLOWED = /^https:\/\/(www\.google\.com\/maps|maps\.google\.com|www\.openstreetmap\.org|mapy\.cz|en\.mapy\.cz)[/?]/i;

export default class MapEmbed {
  static selector = '.block-map';

  constructor(element) {
    this.frame = element.querySelector('[data-map-src]');
    this.button = element.querySelector('[data-map-load]');

    if (!this.frame || !this.button) {
      return;
    }

    this.button.addEventListener('click', () => this.load());
  }

  load() {
    const src = this.frame.dataset.mapSrc || '';
    if (!ALLOWED.test(src)) {
      return;
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = this.frame.dataset.mapTitle || 'Mapa';
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'no-referrer-when-downgrade';
    iframe.allowFullscreen = true;
    this.frame.replaceChildren(iframe);
  }
}
