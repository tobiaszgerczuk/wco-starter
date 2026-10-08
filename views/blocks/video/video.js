// Loads the YouTube / Vimeo player only after a click, so no third-party request is made before.
const ALLOWED = ['https://www.youtube-nocookie.com/', 'https://player.vimeo.com/'];

export default class Video {
  static selector = '.block-video';

  constructor(element) {
    this.player = element.querySelector('[data-video-embed]');
    this.button = element.querySelector('[data-video-play]');

    if (!this.player || !this.button) {
      return;
    }

    this.button.addEventListener('click', () => this.load());
  }

  load() {
    const src = this.player.dataset.videoEmbed || '';
    if (!ALLOWED.some((prefix) => src.startsWith(prefix))) {
      return;
    }

    const frame = document.createElement('iframe');
    frame.src = src;
    frame.title = this.player.dataset.videoTitle || 'Wideo';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    this.button.replaceWith(frame);
    frame.focus();
  }
}
