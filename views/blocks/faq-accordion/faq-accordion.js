export default class FaqAccordion {
  static selector = '.block-faq-accordion';

  constructor(element) {
    this.element = element;
    this.items = [...this.element.querySelectorAll('[data-accordion-item]')];
    this.init();
  }

  init() {
    if (!this.items.length) {
      return;
    }

    this.items.forEach((item) => {
      const trigger = item.querySelector('[data-accordion-trigger]');
      const panel = item.querySelector('[data-accordion-panel]');

      if (!trigger || !panel) {
        return;
      }

      trigger.addEventListener('click', () => {
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';

        this.items.forEach((otherItem) => {
          const otherTrigger = otherItem.querySelector('[data-accordion-trigger]');
          const otherPanel = otherItem.querySelector('[data-accordion-panel]');

          if (!otherTrigger || !otherPanel) {
            return;
          }

          otherTrigger.setAttribute('aria-expanded', 'false');
          otherPanel.hidden = true;
        });

        if (!isExpanded) {
          trigger.setAttribute('aria-expanded', 'true');
          panel.hidden = false;
        }
      });
    });
  }
}
