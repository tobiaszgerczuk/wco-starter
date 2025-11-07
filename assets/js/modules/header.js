export default class Header {
    constructor() {
      this.header = document.querySelector('header');
      this.menuToggle = document.querySelector('[data-toggle="menu"]');
      this.menu = document.querySelector('[data-menu]');
  
      if (this.menuToggle && this.menu) {
        this.menuToggle.addEventListener('click', this.toggleMenu.bind(this));
      }
  
      window.addEventListener('scroll', this.handleScroll.bind(this));
    }
  
    toggleMenu() {
      this.menu.classList.toggle('is-open');
      this.menuToggle.classList.toggle('is-open');
    }
  
    handleScroll() {
      if (!this.header) return;
      if (window.scrollY > 50) {
        this.header.classList.add('is-scrolled');
      } else {
        this.header.classList.remove('is-scrolled');
      }
    }
  }
  