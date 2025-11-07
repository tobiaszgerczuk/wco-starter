/**
 * LazyLoader — prosty, obiektowy system lazy-loadingu obrazów
 *
 * ✅ Obsługuje:
 *  - <img class="lazy" data-src="...">
 *  - automatyczne ładowanie po wejściu w viewport (IntersectionObserver)
 *  - fallback dla starszych przeglądarek
 *  - fade-in po załadowaniu
 *
 * ⚙️ Użycie:
 *  1. W Twigu/HTML dodaj obrazek z atrybutem data-src:
 *     <img class="lazy" data-src="{{ image.url }}" alt="{{ image.alt }}">
 *
 *  2. Zainicjuj w JS:
 *     import LazyLoader from './modules/LazyLoader';
 *     document.addEventListener('DOMContentLoaded', () => new LazyLoader());
 *
 *  3. (Opcjonalnie) Dodaj efekt w SCSS:
 *     img.lazy { opacity:0; filter:blur(6px); transition:opacity .4s, filter .4s; }
 *     img.loaded.visible { opacity:1; filter:blur(0); }
 *
 * 🔧 Opcje w konstruktorze:
 *     new LazyLoader(selector, { rootMargin: '150px', threshold: 0.1 });
 */
export default class LazyLoader {
    /**
     * @param {string} selector - selektor obrazów do obserwacji
     * @param {object} options  - konfiguracja IntersectionObservera
     */
    constructor(selector = 'img.lazy[data-src]', options = {}) {
      this.selector = selector;
      this.rootMargin = options.rootMargin || '100px';
      this.threshold = options.threshold || 0;
      this.images = Array.from(document.querySelectorAll(this.selector));
  
      if (!this.images.length) return;
  
      if ('IntersectionObserver' in window) {
        this.initObserver();
      } else {
        this.initFallback();
      }
    }
  
    /** Tworzy IntersectionObservera i zaczyna obserwować obrazy */
    initObserver() {
      const config = {
        root: null,
        rootMargin: this.rootMargin,
        threshold: this.threshold,
      };
  
      this.observer = new IntersectionObserver(this.onIntersection.bind(this), config);
      this.images.forEach(img => this.observer.observe(img));
    }
  
    /** Callback: wywoływany, gdy obrazek wejdzie w viewport */
    onIntersection(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.loadImage(entry.target);
          this.observer.unobserve(entry.target);
        }
      });
    }
  
    /** Ładuje pojedynczy obrazek */
    loadImage(img) {
      const src = img.dataset.src;
      if (!src) return;
  
      img.src = src;
      img.removeAttribute('data-src');
      img.classList.remove('lazy');
      img.classList.add('loaded');
  
      // Po załadowaniu — fade-in
      img.addEventListener('load', () => img.classList.add('visible'), { once: true });
    }
  
    /** Fallback: dla przeglądarek bez IntersectionObserver */
    initFallback() {
      const loadOnScroll = () => {
        this.images.forEach(img => {
          const rect = img.getBoundingClientRect();
          if (rect.top < window.innerHeight + 100) this.loadImage(img);
        });
      };
      window.addEventListener('scroll', loadOnScroll);
      loadOnScroll();
    }
  }
  