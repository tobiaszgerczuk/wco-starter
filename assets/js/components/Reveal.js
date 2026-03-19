class Reveal {
  constructor(selector = '[data-reveal]') {
    this.selector = selector;
    this.elements = [...document.querySelectorAll(this.selector)];

    if (!this.elements.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      this.elements.forEach((element) => {
        element.classList.add('is-reveal-ready', 'is-revealed');
      });
      return;
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          const element = entry.target;
          element.classList.add('is-revealed');

          if (element.dataset.revealOnce !== 'false') {
            this.observer.unobserve(element);
          }
        });
      },
      {
        rootMargin: '0px 0px -10% 0px',
        threshold: 0.15,
      }
    );

    this.elements.forEach((element) => {
      this.prepare(element);
      this.observer.observe(element);
    });
  }

  prepare(element) {
    const duration = this.parseMsValue(element.dataset.revealDuration, 600);
    const delay = this.parseMsValue(element.dataset.revealDelay, 0);

    element.style.setProperty('--reveal-duration', `${duration}ms`);
    element.style.setProperty('--reveal-delay', `${delay}ms`);
    element.classList.add('is-reveal-ready');
  }

  parseMsValue(value, fallback) {
    const parsed = Number.parseInt(value || '', 10);

    if (Number.isNaN(parsed) || parsed < 0) {
      return fallback;
    }

    return parsed;
  }
}

export default Reveal;
