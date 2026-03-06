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
    constructor(selector = 'img.wco-lazy-image[data-src], picture.wco-lazy-media', options = {}) {
      this.selector = selector;
      this.rootMargin = options.rootMargin || '100px';
      this.threshold = options.threshold || 0;
      this.images = Array.from(document.querySelectorAll(this.selector));
  
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
      this.images.forEach(media => this.observer.observe(media));
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
    loadImage(media) {
      const img = media.tagName.toLowerCase() === 'picture'
        ? media.querySelector('img[data-src]')
        : media;
      if (!img) return;

      const src = img.dataset.src;
      if (!src) return;
  
      if (media.tagName.toLowerCase() === 'picture') {
        media.querySelectorAll('source[data-srcset]').forEach(source => {
          if (source.dataset.srcset) {
            source.setAttribute('srcset', source.dataset.srcset);
            source.removeAttribute('data-srcset');
          }
        });
      }

      const dataSrcset = img.dataset.srcset;
      const dataSizes = img.dataset.sizes;

      img.src = src;
      if (dataSrcset) {
        img.setAttribute('srcset', dataSrcset);
      }
      if (dataSizes) {
        img.setAttribute('sizes', dataSizes);
      }
      img.removeAttribute('data-src');
      img.classList.remove('wco-lazy-image');
      img.removeAttribute('data-srcset');
      img.removeAttribute('data-sizes');
      img.classList.add('loaded');
  
      // Po załadowaniu — fade-in
      img.addEventListener('load', () => img.classList.add('visible'), { once: true });
    }
  
    /** Fallback: dla przeglądarek bez IntersectionObserver */
    initFallback() {
      const loadOnScroll = () => {
      this.images.forEach(media => {
        const img = media.tagName.toLowerCase() === 'picture'
          ? media.querySelector('img[data-src]')
          : media;
        if (!img) {
          return;
        }

        const rect = media.getBoundingClientRect();
          if (rect.top < window.innerHeight + 100) this.loadImage(media);
        });
      };
      window.addEventListener('scroll', loadOnScroll);
      loadOnScroll();
    }
  }
  
