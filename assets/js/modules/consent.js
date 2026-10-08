// Cookie consent banner. Works with the Google Consent Mode v2 defaults printed in <head> by
// app/Core/Tracking.php. Cookie format: <version>.a<0|1>.m<0|1> (analytics, marketing).
export default class Consent {
  constructor() {
    this.banner = document.querySelector('[data-consent-banner]');
    if (!this.banner) {
      return;
    }

    this.version = this.banner.dataset.version;
    this.cookieName = this.banner.dataset.cookie || 'wco_consent';
    this.details = this.banner.querySelector('[data-consent-details]');
    this.toggle = this.banner.querySelector('[data-consent-toggle]');
    this.saveButton = this.banner.querySelector('[data-consent-action="save"]');
    this.checkboxes = [...this.banner.querySelectorAll('[data-consent-category]')];
    this.loaded = new Set();

    this.bind();

    const stored = this.read();
    if (stored) {
      this.injectScripts(stored);
      this.banner.hidden = true;
    } else {
      this.open(false);
    }
  }

  bind() {
    this.banner.querySelector('[data-consent-action="accept"]').addEventListener('click', () => this.save({ analytics: true, marketing: true }));
    this.banner.querySelector('[data-consent-action="reject"]').addEventListener('click', () => this.save({ analytics: false, marketing: false }));
    this.saveButton.addEventListener('click', () => this.save(this.fromCheckboxes()));
    this.toggle.addEventListener('click', () => this.showDetails(this.details.hidden));

    document.querySelectorAll('[data-consent-open]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        this.open(true);
      });
    });
  }

  read() {
    const match = document.cookie.match(new RegExp(`(?:^|; )${this.cookieName}=([^;]*)`));
    const parts = match ? match[1].split('.') : [];

    if (parts[0] !== this.version) {
      return null;
    }

    return { analytics: parts[1] === 'a1', marketing: parts[2] === 'm1' };
  }

  write(state) {
    const value = `${this.version}.a${state.analytics ? 1 : 0}.m${state.marketing ? 1 : 0}`;
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${this.cookieName}=${value}; Max-Age=${60 * 60 * 24 * 365}; Path=/; SameSite=Lax${secure}`;
  }

  fromCheckboxes() {
    const state = { analytics: false, marketing: false };
    this.checkboxes.forEach((box) => {
      state[box.name] = box.checked;
    });

    return state;
  }

  open(withDetails) {
    const current = this.read() || { analytics: false, marketing: false };
    this.checkboxes.forEach((box) => {
      box.checked = Boolean(current[box.name]);
    });

    this.showDetails(withDetails);
    this.banner.hidden = false;
    this.banner.querySelector('button:not([hidden])')?.focus({ preventScroll: true });
  }

  showDetails(show) {
    this.details.hidden = !show;
    this.saveButton.hidden = !show;
    this.toggle.setAttribute('aria-expanded', String(show));
  }

  save(state) {
    const previous = this.read();
    this.write(state);
    this.update(state);
    this.banner.hidden = true;

    // Scripts that were already injected cannot be taken back; reload so they are not running.
    if (previous?.analytics && !state.analytics && this.loaded.size) {
      window.location.reload();
      return;
    }

    this.injectScripts(state);
  }

  /** Tells Google Consent Mode and GTM triggers about the new choice. */
  update(state) {
    window.dataLayer = window.dataLayer || [];
    const granted = (value) => (value ? 'granted' : 'denied');
    const consent = {
      ad_storage: granted(state.marketing),
      ad_user_data: granted(state.marketing),
      ad_personalization: granted(state.marketing),
      analytics_storage: granted(state.analytics),
    };

    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', consent);
    }
    window.dataLayer.push({ event: 'wco_consent_update', consent_analytics: state.analytics, consent_marketing: state.marketing });
  }

  /** Replays admin-provided scripts parked in <template data-wco-consent="..."> once allowed. */
  injectScripts(state) {
    document.querySelectorAll('template[data-wco-consent]').forEach((template) => {
      if (!state[template.dataset.wcoConsent] || this.loaded.has(template)) {
        return;
      }

      this.loaded.add(template);
      const target = template.dataset.target === 'head' ? document.head : document.body;

      template.content.childNodes.forEach((node) => {
        if (node.nodeName !== 'SCRIPT') {
          target.appendChild(node.cloneNode(true));
          return;
        }

        const script = document.createElement('script');
        [...node.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
        script.text = node.textContent;
        target.appendChild(script);
      });
    });
  }
}
