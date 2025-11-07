/**
 * Klasa Parallax
 * -----------------
 * Umożliwia tworzenie efektu paralaksy dla sekcji z tłem.
 * 
 * 🔧 Jak używać:
 * 1. Dodaj atrybut `data-parallax` do elementu, którego tło ma się poruszać.
 *    Przykład w HTML:
 *       <section data-parallax style="background-image: url('img/bg.jpg');">
 *         <div class="container">
 *           <h2>Moja sekcja z paralaksą</h2>
 *         </div>
 *       </section>
 *
 * 2. Upewnij się, że tło sekcji jest ustawione w CSS z `background-attachment: fixed` lub `background-size: cover`.
 *    Przykład CSS:
 *       [data-parallax] {
 *         background-size: cover;
 *         background-repeat: no-repeat;
 *         background-position: center;
 *       }
 *
 * 3. Zainicjuj efekt w JS (np. w `main.js`):
 *       import Parallax from './modules/Parallax.js';
 *       new Parallax('[data-parallax]', 0.4);
 *
 * 🔹 Parametry konstruktora:
 *    - selector (string): selektor elementów, domyślnie '[data-parallax]'
 *    - speed (number): prędkość przesuwania tła (im mniejsza wartość, tym wolniejszy ruch)
 *
 * 📘 Przykład pełny:
 *    <section data-parallax style="background-image: url('images/mountains.jpg'); height: 500px;"></section>
 *    <script>
 *      import Parallax from './modules/Parallax.js';
 *      new Parallax('[data-parallax]', 0.3);
 *    </script>
 */

export default class Parallax {
    constructor(selector = '[data-parallax]', speed = 0.4) {
      this.sections = document.querySelectorAll(selector);
      this.speed = speed;
  
      if (this.sections.length) {
        document.addEventListener('scroll', this.handleScroll.bind(this));
      }
    }
  
    handleScroll() {
      this.sections.forEach(section => {
        const offset = window.scrollY - section.offsetTop;
        const yPos = -(offset * this.speed);
        section.style.backgroundPosition = `center calc(50% + ${yPos}px)`;
      });
    }
  }
  