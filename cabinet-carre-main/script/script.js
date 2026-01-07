const burger = document.querySelector('.hamburger');
const menu = document.getElementById('mobileMenu');
const overlay = document.querySelector('.nav-overlay');

function toggleMenu(open){
  const willOpen = open ?? !menu.classList.contains('open');
  menu.classList.toggle('open', willOpen);
  burger.classList.toggle('is-open', willOpen);
  burger.setAttribute('aria-expanded', String(willOpen));
  overlay.classList.toggle('show', willOpen);
  if (willOpen) {
    overlay.removeAttribute('hidden');
    document.body.classList.add('menu-open');
  } else {
    document.body.classList.remove('menu-open');
    overlay.setAttribute('hidden', '');
  }
}

burger.addEventListener('click', () => toggleMenu());
overlay.addEventListener('click', () => toggleMenu(false));
// Fermer avec Échap
window.addEventListener('keydown', (e) => { if (e.key === 'Escape') toggleMenu(false); });
// Fermer quand on clique un lien
menu.addEventListener('click', (e) => {
  if (e.target.tagName === 'A') toggleMenu(false);
});