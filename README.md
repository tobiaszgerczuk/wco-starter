# WCO Starter Theme

Starter motywu WordPress oparty o:
- Timber + Twig
- natywne bloki WordPressa (bez ACF)
- SCSS + Webpack
- OOP + PSR-4
- opcjonalne integracje WooCommerce

To README ma służyć jako szybka instrukcja startu. Jeśli wracasz do tego startera po czasie, zacznij od sekcji `Szybki start`.

## Szybki start

W katalogu motywu:

```bash
composer install
npm install
npm run build
```

Potem w WordPressie:
- aktywuj motyw
- upewnij się, że działa `Timber` (`composer install`)
- w edytorze sprawdź kategorię `WCO Blocks`

Do pracy developerskiej:

```bash
npm run dev
```

## Wymagania

- WordPress 6+
- PHP 8+
- Composer
- Node.js / npm
- `Timber`

Ważne:
- bez `Timber` motyw nie ruszy
- żadne wtyczki poza `Timber` nie są wymagane; motyw nie używa ACF
- jeśli `WooCommerce` nie jest aktywne, motyw nadal działa, ale bez funkcji sklepowych

## Pierwsze uruchomienie projektu

Jeśli tworzysz nowy projekt na bazie startera:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
```

To polecenie:
- zmienia `Theme Name`
- zmienia `Text Domain`
- zmienia slug techniczny motywu
- podmienia namespace PHP i podstawowe identyfikatory
- próbuje zmienić nazwę katalogu motywu

Po rename:

```bash
composer dump-autoload -o
npm run build
```

Opcje pomocnicze:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --dry-run
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --no-rename-dir
```

## Najważniejsze komendy

```bash
npm run dev
npm run build
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
npm run create-block -- hero-banner "Hero Banner"
```

## Ustawienia motywu

Własny panel w menu administratora: **Ustawienia motywu** (zakładki: Wygląd, Śledzenie i zgody, Social i SEO, Banery i CTA, Treści zastępcze). Wartości trafiają do jednej opcji `wco_starter_settings`; każda zakładka zapisuje się osobno. Dostęp: `manage_options`.

Wartości zapisane wcześniej przez stronę opcji ACF (`options_<nazwa>`) są nadal odczytywane jako zapas, więc po przejściu ze starszej wersji nic nie znika, dopóki ich nie nadpiszesz.

Własny kod `<head>` i `</body>` (zakładka Śledzenie i zgody → Skrypty) renderuje się przez `wp_head` i `wp_footer`. Zapis kodu bez filtrowania wymaga uprawnienia `unfiltered_html`.

Pola panelu opisuje jedna tablica w `WCO\Starter\Core\Settings::schema()`. Nowe ustawienie dodajesz tam jednym wpisem (typy: text, url, textarea, code, toggle, select, number, color, image, link) i czytasz przez `Settings::get('nazwa', $default)`.

## Tworzenie bloków

Nowy blok generujesz komendą:

```bash
npm run create-block -- promo "Promo"
```

Generator tworzy blok natywny (bez ACF):

```text
views/blocks/promo/block.json         # nazwa, ustawienia (wco.controls), ramka edytora (wco.frame)
views/blocks/promo/promo.twig         # HTML sekcji
views/blocks/promo/_promo.scss        # style (ładowane tylko dla tego bloku)
views/blocks/promo/promo.template.php # domyślna treść (inner_template) i dozwolone bloki
```

Presety:

```bash
npm run create-block -- promo "Promo"                   # sekcja z treścią edytowaną w podglądzie
npm run create-block -- team "Team" --preset=items      # blok nadrzędny + <slug>-item
```

Po wygenerowaniu: `npm run build`, potem wstaw blok w edytorze (kategoria `WCO Blocks`).

### Jak działa blok natywny

- `block.json` z `"wco": { "native": true }` jest rejestrowany przez `NativeBlocks` — nie trzeba nic dopisywać w PHP.
- **Ustawienia** to atrybuty bloku (`attributes`), a ich kontrolki w panelu bocznym opisuje `wco.controls` (typy: select, toggle, number, range, text, image, category, link; `showWhen` pokazuje pole warunkowo). `"sectionSettings": true` dodaje wspólne ustawienia sekcji (tło, szerokość, ID, odstępy).
- **Treść** to InnerBlocks; domyślny układ w `<slug>.template.php` (`inner_template`, `inner_allowed`).
- **Wygląd w edytorze** opisuje `wco.frame`: `inner` (klasa wrappera, ze składnią `{atrybut:domyślna}` i `{atrybut? klasa}`), `head` + `after` (nagłówek i element dynamiczny), `item` (blok potomny), `leaf` (blok bez treści).
- **Dane dynamiczne** (zapytania, schema, parsowanie URL) dodajesz w `<slug>.context.php`: zwraca funkcję `fn(array $fields, string $content): array`, której wynik trafia do Twiga.
- Nazwy bloków mają prefiks `acf/` (np. `acf/hero`) tylko po to, by zapisana już treść nadal się renderowała; treść zapisana przez dawne bloki ACF jest odczytywana z atrybutu `data`.
- Własny, niestandardowy edytor dla bloku (jak w `testimonials-slider`) dopisujesz w `assets/js/native-blocks.js` w obiekcie `FRAMES`.

## Wariant sklepu (WooCommerce)

Motyw ma gotowy sklep: archiwum produktów (kategorie jako chipy, sortowanie, paginacja), karta produktu, strona produktu (galeria, ocena, warianty, zakładki, produkty powiązane) i wzorzec strony głównej sklepu.

- **Szablony:** `woocommerce/archive-product.php` i `woocommerce/single-product.php` budują kontekst, a HTML jest w `views/woocommerce/*.twig`. Koszyk, zamówienie i moje konto to zwykłe strony z shortcode'ami WooCommerce, ostylowane w `assets/scss/woocommerce/_shop.scss`.
- **Karta produktu:** `views/modules/product-card.twig` (dane z `Shop::card()`), używana w archiwum, na stronie produktu i w blokach.
- **Bloki:** `Produkty` (najnowsze, wyróżnione, promocje, bestsellery lub z kategorii) i `Kategorie produktów`.
- **Wzorzec:** `Shop home` (kategoria wzorców `wco-sections`): hero, kategorie, bestsellery, zalety, promocje, CTA.
- **Dodawanie do koszyka:** przycisk `.js-add-to-cart` na kartach używa AJAX WooCommerce i mini-koszyka w nagłówku; produkty zmienne i niedostępne prowadzą na stronę produktu.

### Demo z importem

W menu **Ustawienia motywu → Demo sklepu** jednym kliknięciem importujesz: 4 kategorie, 12 produktów z grafikami zastępczymi (część w promocji, jeden niedostępny), stronę główną ze wzorca `Shop home` ustawioną jako główna oraz menu główne. Import jest powtarzalny (pomija istniejące elementy), a wszystko, co utworzył, jest oznaczone meta `_wco_demo`, więc przycisk **Usuń dane demo** cofa import (łącznie z poprzednimi ustawieniami strony głównej), nie ruszając treści dodanych ręcznie.

Z linii poleceń: `wp eval 'wp_set_current_user(1); WCO\Starter\Core\DemoImport::run();'`.

## Tracking i zgoda na cookies (Ustawienia motywu → Śledzenie, Cookies i zgoda)

Całość obsługuje `app/Core/Tracking.php`; baner to `views/partials/cookie-banner.twig` + `assets/js/modules/consent.js`.

- **GTM**: wpisz ID (`GTM-XXXXXX`) w zakładce Śledzenie. Skrypt trafia na sam początek `<head>`, a `<noscript>` z iframe zaraz po `<body>` (szablon wywołuje `wp_body_open`). ID i GA4 są walidowane, nieprawidłowe nie są wypisywane.
- **Consent Mode v2**: przy włączonym banerze przed GTM drukowane są ustawienia domyślne (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage` = `denied`, `wait_for_update` 500 ms). Dla powracającego odwiedzającego domyślne wartości wynikają z jego cookie, więc nie startuje od „denied". Po wyborze wysyłany jest `gtag('consent', 'update', …)` oraz zdarzenie `wco_consent_update` do `dataLayer` (do wyzwalaczy w GTM).
- **Baner**: „Akceptuję wszystkie” i „Tylko niezbędne” mają jednakową wagę, „Dostosuj” pokazuje kategorie (niezbędne, analityczne, marketingowe). Wybór zapisuje cookie `wco_consent` na 12 miesięcy w formacie `<wersja>.a<0|1>.m<0|1>`. Stopka ma link „Ustawienia cookies” (`data-consent-open`), który otwiera baner ponownie; ten sam atrybut możesz dać dowolnemu linkowi. Zmiana pola „Wersja zgody” pyta wszystkich ponownie.
- **Dodatkowe skrypty (head / przed `</body>`)** są przy włączonym banerze trzymane w `<template>` i wstawiane dopiero po zgodzie na cookies analityczne. Po wycofaniu zgody strona się przeładowuje, żeby wstrzyknięte skrypty przestały działać.
- **Pomijanie redaktorów**: domyślnie zalogowani użytkownicy z prawem edycji nie ładują śledzenia (opcja do wyłączenia).
- **Baner pokazuje się tylko, gdy coś jest skonfigurowane** (GTM, GA4 lub dodatkowe skrypty). Jeśli zgodą zarządzasz w GTM własnym CMP, wyłącz „Baner zgody na cookies”: wtedy motyw niczego nie blokuje ani nie zmienia.
- **Ograniczenie**: iframe `<noscript>` nie może przenieść zgody (brak JS), więc przy rygorystycznym RODO wyłącz opcję „GTM noscript iframe”.

## Panel wyglądu (Ustawienia motywu → Wygląd)

Zakładki: **Kolory**, **Typografia**, **Logo i nagłówek**, **Kształt**. Całość obsługuje `app/Core/Appearance.php`.

- **Kolory** (główny, drugi, akcent, tekst, tekst pomocniczy, tło, powierzchnia, obramowania, kolor główny po najechaniu — liczony automatycznie, jeśli pusty) trafiają do zmiennych CSS `:root` (`--color-*`) oraz do presetów WordPressa (`--wp--preset--color--*`). Przyciski, nadtytuły, tła sekcji i cały motyw czytają te zmienne, więc zmiana działa wszędzie, także w edytorze blokowym. Wartości domyślne są w `Appearance::DEFAULTS` i odpowiadają `assets/scss/base/_variables.scss`.
- **Typografia**: czcionka nagłówków i tekstu z katalogu (`Appearance::FONTS`, dopisujesz kolejne jedną linią: nazwa, specyfikacja Google Fonts, czy szeryfowa) oraz bazowy rozmiar. Domyślnie czcionki są **hostowane lokalnie**: po zapisie ustawień pobieranie plików woff2 (podzbiory `latin` i `latin-ext`, więc polskie znaki działają) do `uploads/wco-fonts`, bez zapytań do Google przy wyświetlaniu strony. Gdy pobranie się nie uda albo przełącznik jest wyłączony, używany jest link Google Fonts. Dla innych alfabetów (np. cyrylica) dopisz podzbiór w `Appearance::build_local_fonts()`.
- **Logo i nagłówek**: logo (wysokość ustawiasz osobno), zachowanie nagłówka (przyklejony / statyczny), przezroczysty na górze strony, zmniejszanie przy przewijaniu. Opcje wracają jako klasy `<body>`: `header-static`, `header-solid`, `header-no-shrink` (style w `assets/scss/layout/_header.scss`). Favicon ustawia się natywnie w Wygląd → Dostosuj → Tożsamość witryny.
- **Kształt**: zaokrąglenia przycisków, kart i obrazów (`--radius-sm/md/lg`). Zmienne SCSS `$radius-*` są teraz zmiennymi CSS z wartością zapasową.

Dodanie nowej opcji: wpis w `Settings::schema()` (zakładka Wygląd), wartość domyślna w `Appearance::DEFAULTS`, a zmienną CSS w `Appearance::css()`.

## Bloki z edycją w podglądzie (InnerBlocks)

Domyślny sposób budowania sekcji w tym starterze: **ustawienia to atrybuty bloku, a treść to zwykłe bloki WP wewnątrz bloku**. Dzięki temu teksty, obrazy i przyciski klikasz i edytujesz bezpośrednio na podglądzie sekcji, bez ołówka i panelu bocznego.

- Blok jest rejestrowany przez `block.json` (klucze `name` i `wco.native`) — `NativeBlocks::register()`.
- Treść startową definiuje `<slug>.template.php` (klucz `inner_template`): tablica `[nazwa, atrybuty, dzieci]`. Pomocnicze: `BlockContext::head_template()` (nadtytuł + nagłówek + lead), `BlockContext::buttons_template()`.
- W Twigu wstawiasz `{{ inner_blocks(inner_template) }}` (opcjonalnie `inner_blocks(inner_template, inner_allowed)` — lista dozwolonych bloków). W edytorze ten sam układ buduje `native-blocks.js` według `wco.frame`, a na froncie funkcja zwraca zapisaną treść w tym samym `div.acf-innerblocks-container`, którego używa edytor — dzięki temu front i edytor mają identyczny DOM (`.blok > .acf-innerblocks-container > dzieci`).
- Listy elementów (kafelki, FAQ, opinie, statystyki) to bloki potomne (`"parent": ["acf/features"]` w `block.json`), a nie repeatery — dodajesz, przestawiasz i usuwasz je w podglądzie.
- Układ siatki piszesz mixinem `inner-layout` (celuje w `> .acf-innerblocks-container`), który działa i na froncie, i w edytorze (`@include inner-layout { @include grid-columns(3); }`).
- Klasy `className` w szablonie (`is-eyebrow`, `is-lead`, `is-span-all`, `is-value` …) są stylowane w `assets/scss/components/_block-content.scss` i w SCSS bloku.
- Przyciski to natywny `core/button` z wariantami `secondary`, `outline`, `link` (style w `components/_buttons.scss`).

### Wspólne ustawienia sekcji

Tło, szerokość treści, ID bloku i odstępy to **jeden** zestaw atrybutów (`NativeBlocks::SECTION_ATTRIBUTES`) z panelami w `native-blocks.js`. Dołączasz go do bloku jedną linijką w `block.json`:

```json
"wco": { "sectionSettings": true }
```

Nowy blok nie potrzebuje własnej kopii pól. Pola specyficzne dla bloku (kolumny, wyrównanie…) opisuje `wco.controls` w `block.json`. Wartości domyślne ustawień sekcji są w `BlockContext::SECTION_DEFAULTS` (działają też dla bloków wstawionych ze wzorca).

### Gotowe sekcje

`hero`, `section-heading`, `text-image`, `cta`, `separator`, `features` (+`feature-item`), `faq` (+`faq-item`, akordeon i dane strukturalne FAQPage), `testimonials` (+`testimonial-item`), `stats` (+`stat-item`), `logos`, `gallery`, `latest-posts` (z opcjonalnym „Załaduj więcej”), `pricing` (+`pricing-item`, wyróżniony pakiet z plakietką), `team` (+`team-member`), `timeline` (+`timeline-item`, jedna strona lub naprzemiennie), `video`, `map`, `contact`. Wcześniejsze: `container-group`, `two-columns`, `spacer`, `testimonials-slider`.

### Wideo, mapa i formularz

- **`video`**: adres YouTube, Vimeo lub plik `.mp4`/`.webm`. Dla YouTube i Vimeo pokazuje się najpierw okładka z przyciskiem odtwarzania (opcjonalny obraz okładki), a odtwarzacz (`youtube-nocookie.com`, Vimeo z `dnt=1`) wczytuje się dopiero po kliknięciu, więc przed zgodą nie ma żadnego połączenia z zewnętrznym serwisem. Dozwolone adresy iframe są sprawdzane w PHP i w `video.js`.
- **`map`**: wpisujesz adres albo własny link osadzenia (Google Maps, OpenStreetMap, Mapy.cz; inne hosty są odrzucane). Mapa wczytuje się po kliknięciu „Wyświetl mapę”, obok jest link „Otwórz w Google Maps”. Wysokość 300–600 px.
- **`contact`**: dane kontaktowe edytujesz w podglądzie, a formularz to pojedynczy shortcode wklejony w ustawieniach bloku (np. `[contact-form-7 id="123" title="Kontakt"]`). Style CF7 są w `components/_forms.scss`. Blok nie ma własnej obsługi wysyłki, więc potrzebuje wtyczki formularzy.

### Style w edytorze (żeby czcionki motywu nie wchodziły do interfejsu WordPressa)

Style motywu (`public/style.css`: typografia, `body`, nagłówki, linki) trafiają do edytora **tylko do podglądu treści**, przez `add_editor_style()` w `Theme::supports()`; WordPress zawęża je wtedy do `.editor-styles-wrapper`. Arkusze bloków (`public/blocks/<slug>/<slug>.css`) są ładowane także w edytorze, czyli na całym ekranie admina, dlatego `Registry::register_block_style_handle()` nie daje im zależności od `style.css` w panelu (`is_admin()`). Nie dodawaj reguł na `body`, `h1`–`h6`, `a` ani `p` do arkuszy ładowanych w adminie, bo zmienią wygląd panelu.

### Wzorce (patterns)

Gotowe układy stron są w `patterns/*.php` (`landing`, `about`, `faq`, `blog-intro`, `pricing`, `contact`, `team`) i pojawiają się w edytorze w kategorii **WCO sections**. WordPress cache'uje listę wzorców motywu — po dodaniu pliku wyczyść ją:

```bash
docker compose run --rm wp-cli wp eval 'wp_get_theme()->delete_pattern_cache();'
```

## Struktura bloku

Każdy blok trzyma wszystko obok siebie:

```text
views/blocks/nazwa-bloku/
  block.json
  nazwa-bloku.twig
  _nazwa-bloku.scss
  nazwa-bloku.js
  nazwa-bloku.include.php
```

To jest celowy układ:
- `twig` = markup
- `scss` = styl bloku
- `js` = klasa OOP z vanilla JS
- `include.php` = render Timbera
- `block.json` = metadane bloku do rejestracji

Dodatkowo w `views` masz też miejsce na reużywalne partiale:

```text
views/components/
  badge.twig
  button.twig
  heading.twig
  icon.twig
  link.twig
  media.twig
  modal.twig

views/modules/
  breadcrumbs.twig
  cta-banner.twig
  features-grid.twig
  modal-trigger.twig
  posts-list.twig
  section-header.twig
  stats-grid.twig
```

Przykłady użycia:
- `components` = małe, wielokrotnego użytku elementy UI
- `modules` = większe fragmenty sekcji lub powtarzalne układy

Nowe gotowce:
- `components/heading.twig` — spójny nagłówek sekcji (`eyebrow`, `title`, `intro`)
- `components/icon.twig` — wrapper dla ikon (np. lucide przez `data-lucide`)
- `components/media.twig` — obraz/video/placeholder z prostym API
- `components/badge.twig` — etykiety/tagi
- `components/link.twig` — link z obsługą `target`, `rel`, `aria-label`
- `modules/cta-banner.twig` — sekcja CTA z heading + button
- `modules/stats-grid.twig` — siatka statystyk
- `modules/features-grid.twig` — siatka cech z ikonami i linkami
- `modules/posts-list.twig` — wspólny listing kart wpisów
- `modules/breadcrumbs.twig` — breadcrumbs do stron wpisów i podstron

## Gotowe bloki startowe

W starterze są już przygotowane przykładowe bloki (pełna lista nowych sekcji — wyżej):
- `testimonials-slider`
- `container-group`
- `spacer`

`testimonials-slider` jest spięty z registry swiperów i ma już gotowy JS, Twig, SCSS i własny edytor opinii (bez ACF).
`spacer` to prosty separator odstępu z osobnym ustawieniem wysokości dla `desktop` i `mobile`.

### Spacer

Blok `spacer` służy do kontrolowanego dodawania pionowych odstępów między sekcjami lub elementami treści.

Dla `desktop` i `mobile` możesz niezależnie wybrać:
- wartość ze skali motywu: `none`, `xs`, `sm`, `md`, `lg`, `xl`
- albo własną wartość w `px`

### Container Group

Blok `container-group` to wrapper do układania układów (sekcji z innymi blokami). W ustawieniach możesz wybrać szerokość kontenera:

- `default` (`$container-default`)
- `wide` (`$container-wide`)
- `medium` (`$container-medium`)
- `narrow` (`$container-narrow`)
- `full` (bez klasy kontenera)

Blok dodatkowo wspiera ustawienia sekcji:
- `section_background_color`
- `section_gap_top`
- `section_gap_bottom`
- `section_space_top`
- `section_space_bottom`

`section_background_color` automatycznie dodaje klasę tła sekcji, jeśli wybierzesz kolor inny niż `none`.

## Swiper

Starter ma przygotowane miejsce na rejestrację sliderów swiperowych:
- `assets/js/modules/SwiperRegistry.js`
- `assets/js/modules/swipers.js`
- `assets/js/front.js`

Workflow:
- `swiper` jest już wpisany do `package.json`
- po `npm install` możesz z niego korzystać od razu
- odkomentowujesz importy w `front.js`
- rejestrujesz slider przez `swipers.register({...})`

Przykład:

```js
swipers.setEngine(Swiper);

swipers.register({
  name: 'testimonials',
  selector: '.js-swiper-testimonials',
  options: {
    modules: [Navigation, Pagination],
    slidesPerView: 1,
    spaceBetween: 24,
  },
});
```

## REST API

Warstwa REST jest rozbita na klasy i centralny rejestr:
- `app/Rest/Api.php` — zbiera klasy endpointów
- `app/Rest/BaseRoute.php` — helper bazowy
- `app/Rest/Contracts/RouteInterface.php` — kontrakt dla endpointów
- `app/Rest/Routes/*` — osobne klasy route

Obecnie są przygotowane:
- `PingRoute`
- `CartRoute`
- `PostsRoute`

Jeśli chcesz dodać nowy endpoint:
1. tworzysz klasę w `app/Rest/Routes/`
2. implementujesz `RouteInterface`
3. dopisujesz klasę do listy w `app/Rest/Api.php`

`PostsRoute` jest używany przez blok `latest-posts` jako baza pod ładowanie kolejnych wpisów.

Endpoint `posts` korzysta też z lekkiego cache helpera, więc może służyć jako wzorzec dla cięższych endpointów.

## Cache helper

W starterze jest lekki helper cache:
- `app/Core/Cache.php`

API:
- `Cache::remember($key, $ttl, fn() => ...)`
- `Cache::forget($key)`

Implementacja:
- najpierw object cache (`wp_cache_*`)
- fallback do transientów

Przykłady użycia:
- cache manifestu assetów w `Templating`
- cache odpowiedzi endpointu `PostsRoute`

## theme.json

Starter ma skonfigurowany `theme.json` (v3), który spina nowoczesne ustawienia edytora:
- kolory
- spacing
- typography
- content width / wide width

Plik:
- `theme.json`

`theme.json` generuje się automatycznie z `assets/scss/base/_variables.scss`:
- przed `npm run build`
- przed `npm run dev`

Ręcznie:

```bash
npm run theme-json:generate
```

To działa równolegle z Twig + blokami natywnymi i poprawia doświadczenie w edytorze Gutenberg.

## Requirements check

Wymagania motywu są zebrane centralnie:
- `app/Core/Requirements.php`

Sprawdzane elementy:
- `Timber` (wymagane, blokujące start)
- `WooCommerce` (opcjonalne, info o wyłączonych funkcjach sklepu)

Notice jest spójny i idzie z jednego miejsca zamiast rozproszonej logiki.

## Standardy developerskie

Dodatkowo:
- `.editorconfig`
- `.nvmrc` (Node 20)

## CF7

Starter ma też bazowe style pod Contact Form 7 w:
- `assets/scss/components/_forms.scss`

To nie jest pełny design formularza projektu, tylko sensowny reset/starter:
- inputy i textarea mają normalne border, padding i focus
- submit dziedziczy styl `.btn`
- walidacja i komunikaty mają podstawowe style
- zgody/checkboxy są uporządkowane

## System styli

Starter ma prosty system design tokens w SCSS.

Główne pliki:
- `assets/scss/base/_variables.scss` — kolory, fonty, breakpointy, kontenery, guttery
- `assets/scss/base/_mixins.scss` — mixiny kontenerów i breakpointów
- `assets/scss/base/_reset.scss` — lekki reset globalny
- `assets/scss/base/_typography.scss` — baza typografii
- `assets/scss/_base.scss` — CSS variables, klasy kontenerów i utilities sekcji

## Kontenery

Dostępne klasy:
- `.container`
- `.container-wide`
- `.container-medium`
- `.container-narrow`

Dostępne mixiny:

```scss
@include container();
@include container(wide);
@include container(medium);
@include container(narrow);
```

Przykład:

```scss
.section {
  @include container(medium);
}
```

## Sekcje i odstępy

Starter ma też prosty system sekcji, jeśli chcesz szybko sterować:
- odstępem od poprzedniej sekcji
- paddingiem wewnątrz sekcji
- tłem sekcji

Bazowe klasy:
- `.section`
- `.container`
- `.section-bg`

Klasy odstępów zewnętrznych:
- `.section-gap-top-none|xs|sm|md|lg|xl`
- `.section-gap-bottom-none|xs|sm|md|lg|xl`

Klasy spacingu wewnętrznego:
- `.section-space-top-none|xs|sm|md|lg|xl`
- `.section-space-bottom-none|xs|sm|md|lg|xl`

Przykład:

```html
<section class="section section-bg section-gap-top-md section-space-top-lg section-space-bottom-lg">
  <div class="container">
    ...
  </div>
</section>
```

Semantyka:
- `section-gap-*` = `margin`
- `section-space-*` = `padding`
- `section-bg` = tło sekcji

W blokach generowanych przez `create-block` te ustawienia są od razu dostępne w panelu bocznym, więc możesz sterować sekcją z poziomu edytora bez ręcznego dopisywania klas.

## Breakpointy

Dostępne mixiny:

```scss
@include mobile { ... }
@include tablet { ... }
@include laptop { ... }
@include desktop-up { ... }

@include respond-down(mobile) { ... }
@include respond-up(tablet) { ... }
@include respond-between(mobile, laptop) { ... }
```

## Gdzie zmieniać style globalne

Jeśli chcesz zmienić:
- szerokość layoutu
- guttery
- breakpointy
- paletę kolorów
- font bazowy

to zaczynasz od:

```text
assets/scss/base/_variables.scss
```

## Gdzie co jest

Najważniejsze katalogi:

```text
app/
assets/
views/
scripts/
public/
```

Co oznaczają:
- `app/` — PHP, rejestracje, bootstrap, logika motywu
- `assets/` — źródła SCSS i JS
- `views/` — Twig i katalogi bloków
- `views/blocks/<slug>/<slug>.template.php` / `<slug>.context.php` — domyślna treść i dane dynamiczne bloku
- `scripts/` — komendy pomocnicze startera
- `public/` — build z webpacka

## Typowy workflow

1. Zmień nazwę startera komendą `rename-theme`.
2. Uruchom `composer install` i `npm install`.
3. Uruchom `npm run build` albo `npm run dev`.
4. Aktywuj motyw.
5. Sprawdź, czy działa `Timber` (`composer install`).
6. Twórz bloki przez `npm run create-block`.
7. Trzymaj style globalne w tokenach, a blokowe obok bloków.

## Debug

Jeśli coś nie działa:

- brak widoków Twig:
  uruchom `composer install`

- brak bloków w Gutenberg:
  sprawdź, czy blok ma `"wco": { "native": true }` w `block.json`

- brak nowych assetów:
  uruchom `npm run build`

- problem po rename motywu:
  uruchom `composer dump-autoload -o`

- problem z WooCommerce:
  bez aktywnego WooCommerce funkcje sklepowe są wyłączone, ale motyw powinien działać

## Uwagi

- `public/` jest wynikiem buildu, nie edytuj go ręcznie
- style bloków i JS bloków trzymaj przy blokach
- jeśli dodajesz nowe globalne tokeny, trzymaj je w `assets/scss/base/_variables.scss`
- jeśli dodajesz override dla pluginu, nie wrzucaj tego do resetu, tylko do odpowiedniego partiala komponentu lub vendora
