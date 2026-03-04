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
