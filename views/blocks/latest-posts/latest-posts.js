export default class LatestPosts {
  static selector = '.block-latest-posts';

  constructor(element) {
    this.element = element;
    this.root = element.querySelector('[data-latest-posts]');
    this.grid = element.querySelector('[data-latest-posts-grid]');
    this.button = element.querySelector('[data-latest-posts-button]');
    this.empty = element.querySelector('[data-latest-posts-empty]');
    this.isLoading = false;

    if (!this.root || !this.grid || !this.button) {
      return;
    }

    this.handleClick = this.handleClick.bind(this);
    this.button.addEventListener('click', this.handleClick);
  }

  get page() {
    return Number(this.root.dataset.page || 1);
  }

  set page(value) {
    this.root.dataset.page = String(value);
  }

  get totalPages() {
    return Number(this.root.dataset.totalPages || 1);
  }

  get restUrl() {
    if (this.root.dataset.restUrl) {
      return this.root.dataset.restUrl;
    }

    if (window.WCO?.restUrl) {
      return window.WCO.restUrl;
    }

    if (window.wpApiSettings?.root) {
      return `${window.wpApiSettings.root.replace(/\/$/, '')}/wco-starter/v1/posts`;
    }

    return '/wp-json/wco-starter/v1/posts';
  }

  async handleClick() {
    if (this.isLoading) {
      return;
    }

    const nextPage = this.page + 1;
    if (nextPage > this.totalPages) {
      this.button.remove();
      return;
    }

    this.isLoading = true;
    this.button.classList.add('is-loading');
    const originalLabel = this.button.textContent;
    this.button.textContent = this.root.dataset.loadingLabel || 'Loading...';

    try {
      const url = new URL(this.restUrl, window.location.origin);
      url.searchParams.set('page', String(nextPage));
      url.searchParams.set('per_page', this.root.dataset.perPage || '3');
      url.searchParams.set('read_more_label', this.root.dataset.readMoreLabel || 'Read more');
      url.searchParams.set('no_image_label', this.root.dataset.noImageLabel || 'No image');

      const response = await fetch(url.toString(), {
        headers: {
          'X-WP-Nonce': window.WCO?.nonce || '',
        },
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const data = await response.json();
      const posts = Array.isArray(data.posts) ? data.posts : [];

      if (!posts.length) {
        this.showEmptyState();
        this.button.remove();
        return;
      }

      this.grid.insertAdjacentHTML('beforeend', posts.map((post) => post.html || this.renderPost(post)).join(''));
      this.page = nextPage;

      if (!data.pagination?.hasMore) {
        this.button.remove();
      } else {
        this.button.textContent = originalLabel;
      }
    } catch (error) {
      this.button.textContent = originalLabel;
      this.button.classList.remove('is-loading');
      this.isLoading = false;
      throw error;
    }

    this.button.classList.remove('is-loading');
    this.isLoading = false;
  }

  renderPost(post) {
    const title = this.escapeHtml(post.title || '');
    const excerpt = this.escapeHtml(post.excerpt || '');
    const permalink = this.escapeAttribute(post.permalink || '#');
    const date = this.escapeHtml(post.date || '');
    const author = this.escapeHtml(post.author || '');
    const image = this.escapeAttribute(post.image || '');
    const imageAlt = this.escapeAttribute(post.imageAlt || post.title || '');
    const readMore = this.escapeHtml(this.root.dataset.readMoreLabel || 'Read more');
    const noImage = this.escapeHtml(this.root.dataset.noImageLabel || 'No image');

    return `
      <article class="post-card">
        <a class="post-card__media" href="${permalink}">
          ${image
            ? `<img src="${image}" alt="${imageAlt}" loading="lazy">`
            : `<span class="post-card__placeholder">${noImage}</span>`}
        </a>
        <div class="post-card__body">
          <div class="post-card__meta">
            ${date ? `<span>${date}</span>` : ''}
            ${author ? `<span>${author}</span>` : ''}
          </div>
          <h3 class="post-card__title"><a href="${permalink}">${title}</a></h3>
          ${excerpt ? `<div class="post-card__excerpt">${excerpt}</div>` : ''}
          <a class="post-card__link" href="${permalink}">${readMore}</a>
        </div>
      </article>
    `;
  }

  showEmptyState() {
    if (this.empty) {
      this.empty.hidden = false;
      return;
    }

    this.grid.insertAdjacentHTML(
      'afterend',
      `<p class="block-latest-posts__empty" data-latest-posts-empty>${this.escapeHtml(this.root.dataset.emptyLabel || 'No posts found.')}</p>`
    );
  }

  escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  escapeAttribute(value) {
    return this.escapeHtml(value);
  }
}
