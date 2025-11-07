export default class WcoShop {
    constructor() {
      this.cartUrl = `${WCO.restUrl}/cart`;
      this.cart = document.getElementById('mini-cart');
      this.toggleBtn = document.getElementById('mini-cart-toggle');
      this.closeBtn = document.getElementById('mini-cart-close');
      this.overlay = document.getElementById('mini-cart-overlay');
      this.content = document.getElementById('mini-cart-content');
      this.countEl = document.getElementById('mini-cart-count');
      this.sidebar = document.getElementById('mini-cart-sidebar');
  
      this.init();
    }
  
    init() {
      this.bindEvents();
      this.updateMiniCart();
      this.bindAddToCart();
    }
  
    bindEvents() {
      this.toggleBtn?.addEventListener('click', () => this.openCartSidebar());
      this.closeBtn?.addEventListener('click', () => this.closeCartSidebar());
      this.overlay?.addEventListener('click', () => this.closeCartSidebar());
    }
  
    bindAddToCart() {
      document.querySelectorAll('.js-add-to-cart').forEach(btn => {
        btn.addEventListener('click', e => {
          e.preventDefault();
          this.addToCart(btn.dataset.productId);
        });
      });
    }
  
    async addToCart(productId) {
      const formData = new FormData();
      formData.append('product_id', productId);
  
      try {
        const res = await fetch('/?wc-ajax=add_to_cart', { method: 'POST', body: formData });
        const text = await res.text();
        if (text.includes('woocommerce-message')) {
          this.showNotice('Produkt dodany do koszyka 🎉');
          this.updateMiniCart();
        }
      } catch (err) {
        this.showNotice('Błąd przy dodawaniu do koszyka ❌');
      }
    }
  
    async updateMiniCart() {
      try {
        const res = await fetch(this.cartUrl);
        const data = await res.json();
  
        this.countEl.textContent = data.count || 0;
        this.content.innerHTML = '';
  
        if (!data.items.length) {
          this.content.innerHTML = '<p class="mini-cart__empty">Koszyk jest pusty.</p>';
          return;
        }
  
        data.items.forEach(item => {
          const el = document.createElement('div');
          el.className = 'mini-cart__item';
          el.innerHTML = `
            <img src="${item.thumb}" alt="${item.name}">
            <div class="name"><a href="${item.link}">${item.name}</a></div>
            <div class="qty">${item.qty} × ${item.price}</div>
            <button class="remove" data-key="${item.key}">&times;</button>
          `;
          this.content.appendChild(el);
        });
  
        this.content.querySelectorAll('.remove').forEach(btn => {
          btn.addEventListener('click', e => this.removeItem(e.target.dataset.key));
        });
      } catch (err) {
        console.error('Błąd koszyka', err);
      }
    }
  
    async removeItem(key) {
      try {
        const formData = new FormData();
        formData.append('cart_item_key', key);
        await fetch('/?wc-ajax=remove_cart_item', { method: 'POST', body: formData });
        this.updateMiniCart();
        this.showNotice('Usunięto produkt z koszyka ❌');
      } catch (err) {
        console.error(err);
      }
    }
  
    openCartSidebar() {
      this.cart.classList.add('is-open');
    }
  
    closeCartSidebar() {
      this.cart.classList.remove('is-open');
    }
  
    showNotice(msg) {
      const n = document.createElement('div');
      n.className = 'wco-notice';
      n.textContent = msg;
      document.body.appendChild(n);
      setTimeout(() => n.classList.add('show'), 10);
      setTimeout(() => {
        n.classList.remove('show');
        setTimeout(() => n.remove(), 300);
      }, 3000);
    }
  }
  