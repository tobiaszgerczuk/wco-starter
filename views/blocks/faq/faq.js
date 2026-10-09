export default class Faq {
  static selector = '.block-faq';
  static counter = 0;

  constructor(element) {
    this.element = element;
    this.multiple = element.dataset.multiple === 'true';
    this.items = [];
    this.init();
  }

  init() {
    this.element.querySelectorAll('.block-faq-item').forEach((item) => this.build(item));

    if (this.element.dataset.openFirst === 'true' && this.items[0]) {
      this.toggle(this.items[0], true);
    }
  }

  build(item) {
    const heading = item.querySelector('h1, h2, h3, h4, h5, h6');
    if (!heading) {
      return;
    }

    // The heading and its answer are siblings inside .acf-innerblocks-container.
    const container = heading.parentElement;

    const id = `faq-panel-${Faq.counter++}`;
    const panel = document.createElement('div');
    panel.className = 'block-faq-item__panel';
    panel.id = id;
    panel.hidden = true;

    [...container.childNodes].filter((node) => node !== heading).forEach((node) => panel.appendChild(node));
    container.appendChild(panel);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'block-faq-item__toggle';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', id);
    button.append(...heading.childNodes);
    heading.appendChild(button);

    const entry = { button, panel };
    button.addEventListener('click', () => this.toggle(entry));
    this.items.push(entry);
  }

  toggle(entry, forceOpen = null) {
    const open = forceOpen ?? entry.panel.hidden;

    if (open && !this.multiple) {
      this.items.forEach((other) => other !== entry && this.set(other, false));
    }

    this.set(entry, open);
  }

  set({ button, panel }, open) {
    button.setAttribute('aria-expanded', String(open));
    panel.hidden = !open;
  }
}
