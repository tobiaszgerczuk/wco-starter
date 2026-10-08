// "Load more" for the Latest Posts block. Cards come pre-rendered from the REST route
// (app/Rest/Routes/PostsRoute.php), so they match the server-rendered markup.
export default class LatestPosts {
  static selector = '.block-latest-posts';

  constructor(element) {
    this.root = element.querySelector('[data-latest-posts]');
    this.grid = element.querySelector('[data-latest-posts-grid]');
    this.button = element.querySelector('[data-latest-posts-button]');
    this.loading = false;

    if (!this.root || !this.grid || !this.button) {
      return;
    }

    this.button.addEventListener('click', () => this.loadMore());
  }

  get data() {
    return this.root.dataset;
  }

  async loadMore() {
    if (this.loading) {
      return;
    }

    const page = Number(this.data.page || 1) + 1;
    const label = this.button.textContent;
    this.loading = true;
    this.button.disabled = true;
    this.button.textContent = this.data.loadingLabel || '…';

    try {
      const url = new URL(this.data.restUrl, window.location.origin);
      url.searchParams.set('page', String(page));
      url.searchParams.set('per_page', this.data.perPage || '3');
      url.searchParams.set('read_more_label', this.data.readMoreLabel || '');
      url.searchParams.set('no_image_label', this.data.noImageLabel || '');
      if (Number(this.data.category) > 0) {
        url.searchParams.set('category', this.data.category);
      }

      const response = await fetch(url.toString());
      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const { posts = [], pagination = {} } = await response.json();
      this.grid.insertAdjacentHTML('beforeend', posts.map((post) => post.html).join(''));
      this.data.page = String(page);

      if (pagination.hasMore) {
        this.button.textContent = label;
        this.button.disabled = false;
      } else {
        this.button.parentElement.remove();
      }
    } catch (error) {
      this.button.textContent = label;
      this.button.disabled = false;
    } finally {
      this.loading = false;
    }
  }
}
