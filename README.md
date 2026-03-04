# 🧩 WCO Starter Theme

Customowy starter motyw WordPressa oparty o:
- **Timber / Twig**
- **ACF Blocks** (definiowane w panelu WP-Admin)
- **SCSS + Webpack** (watch & build)
- **OOP + PSR-4**
- **REST API skeleton**
- Gotowe szablony: `front-page`, `page`, `single`, `archive`, `search`, `404`

---

## 🚀 Instalacja

W katalogu `wp-content/themes`:
```bash
git clone https://github.com/<twoje-repo>/wco-starter.git
cd wco-starter
composer install
npm install
npm run build
```

## 🔁 Zmiana nazwy startera

Jeśli chcesz od razu przemianować starter pod konkretny projekt, uruchom:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
```

To polecenie:
- zmieni `Theme Name`
- zmieni `Text Domain` i slug techniczny
- podmieni namespace PHP, REST namespace i podstawowe identyfikatory
- spróbuje też zmienić nazwę katalogu motywu

Po zakończeniu uruchom jeszcze:

```bash
composer dump-autoload -o
npm run build
```

Opcje pomocnicze:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --dry-run
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --no-rename-dir
```

## 🧱 Tworzenie bloków

Starter obsługuje ACF Blocks renderowane przez Twig i zapisuje field groups do lokalnego `acf-json`.

Aby wygenerować nowy blok:

```bash
npm run create-block -- hero-banner "Hero Banner"
```

To polecenie utworzy:
- `views/blocks/hero-banner/block.json`
- `views/blocks/hero-banner/hero-banner.twig`
- `views/blocks/hero-banner/_hero-banner.scss`
- `views/blocks/hero-banner/hero-banner.js`
- `views/blocks/hero-banner/hero-banner.include.php`
- `acf-json/group_block_hero-banner.json`

Style bloku będą ładowane automatycznie dla tego konkretnego bloku, bez ręcznego dopisywania importu do głównego `style.scss`.
Klasa JS bloku będzie ładowana przez `front.js` automatycznie i zainicjalizuje się tylko wtedy, gdy `.block-hero-banner` istnieje na stronie.

Potem:

```bash
npm run build
```

Opcjonalnie możesz najpierw zobaczyć dry-run:

```bash
npm run create-block -- hero-banner "Hero Banner" --dry-run
```

## 🎨 System styli

Starter ma bazowy system design tokens w SCSS, żeby nie trzymać kontenerów, kolorów i fontów porozrzucanych po komponentach.

Główne pliki:
- `assets/scss/base/_variables.scss` — kolory, fonty, breakpointy, kontenery, guttery
- `assets/scss/base/_mixins.scss` — mixiny kontenerów i breakpointów
- `assets/scss/_base.scss` — CSS variables w `:root` i klasy kontenerów
- `assets/scss/base/_typography.scss` — baza typografii

Dostępne kontenery:
- `.container`
- `.container-wide`
- `.container-medium`
- `.container-narrow`

Mixiny:

```scss
@include container();
@include container(wide);
@include container(medium);
@include container(narrow);

@include mobile { ... }
@include tablet { ... }
@include laptop { ... }
@include desktop-up { ... }

@include respond-down(mobile) { ... }
@include respond-up(tablet) { ... }
@include respond-between(mobile, laptop) { ... }
```

Przykład:

```scss
.section {
  @include container(medium);
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;

  @include tablet {
    grid-template-columns: 1fr;
  }
}
```

Jeśli chcesz zmienić główną szerokość layoutu, font bazowy albo paletę kolorów, zaczynasz od `assets/scss/base/_variables.scss`.
