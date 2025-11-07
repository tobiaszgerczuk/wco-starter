// assets/js/components/CustomBehaviors.js

/**
 * Uniwersalna klasa do customowych zachowań
 * - Smooth scroll
 * - Scroll spy
 * - Back to top
 * - AOS (Animate On Scroll)
 */
class CustomBehaviors {
  constructor(options = {}) {
    this.options = {
      smoothScroll: true,
      scrollOffset: () => 80,
      scrollSpy: false,
      backToTop: false,
      aos: {
        enable: true,
        once: true,
        duration: 600,
        easing: 'ease-out-cubic',
        offset: 100,
        delay: 0,
        anchorPlacement: 'top-bottom'
      },
      ...options
    };

    this.init();
  }

  init() {
    if (this.options.smoothScroll) this.initSmoothScroll();
    if (this.options.scrollSpy) this.initScrollSpy();
    if (this.options.backToTop) this.initBackToTop();
    if (this.options.aos.enable) this.initAOS();
  }

  // ==============================================================
  // 1. SMOOTH SCROLL
  // ==============================================================
  initSmoothScroll() {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href^="#"]');
      if (!link || link.getAttribute('href') === '#') return;

      e.preventDefault();
      this.scrollTo(link.getAttribute('href'));
    });

    if (window.location.hash) {
      setTimeout(() => this.scrollTo(window.location.hash), 100);
    }
  }

  scrollTo(selector) {
    const target = document.querySelector(selector);
    if (!target) return;

    const offset = typeof this.options.scrollOffset === 'function'
      ? this.options.scrollOffset()
      : this.options.scrollOffset;

    const position = target.getBoundingClientRect().top + window.pageYOffset - offset;

    window.scrollTo({ top: position, behavior: 'smooth' });
    history.pushState(null, null, selector);
  }

  // ==============================================================
  // 2. SCROLL SPY
  // ==============================================================
  initScrollSpy() {
    const sections = document.querySelectorAll('[id]');
    const links = document.querySelectorAll('a[href^="#"]');

    if (!sections.length || !links.length) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const id = `#${entry.target.id}`;
            links.forEach(link => {
              link.classList.toggle('is-active', link.getAttribute('href') === id);
            });
          }
        });
      },
      { rootMargin: `-${this.options.scrollOffset()}px 0px -50% 0px` }
    );

    sections.forEach(s => observer.observe(s));
  }

  // ==============================================================
  // 3. BACK TO TOP
  // ==============================================================
  initBackToTop() {
    const btn = document.createElement('button');
    btn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24"><path d="M12 8l-6 6 1.41 1.41L12 10.83l4.59 4.58L18 14l-6-6z" fill="currentColor"/></svg>`;
    btn.className = 'back-to-top';
    btn.setAttribute('aria-label', 'Powrót na górę');
    document.body.appendChild(btn);

    window.addEventListener('scroll', () => {
      btn.classList.toggle('is-visible', window.pageYOffset > 300);
    });

    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ==============================================================
  // 4. AOS – Animate On Scroll
  // ==============================================================
  async initAOS() {
    try {
      const AOS = await import('aos');
      AOS.init(this.options.aos);
    } catch (err) {
      console.warn('AOS nie załadowany:', err);
    }
  }
}



export default CustomBehaviors;