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

const cabinetCarousel = document.getElementById('cabinetCarousel');
const cabinetPrev = document.querySelector('.cabinet-carousel-btn.prev');
const cabinetNext = document.querySelector('.cabinet-carousel-btn.next');
const cabinetDots = document.querySelectorAll('.cabinet-dot');

if (cabinetCarousel && cabinetPrev && cabinetNext && cabinetDots.length) {
  let scrollTimer;

  const getStep = () => {
    const item = cabinetCarousel.querySelector('.cabinet-item');
    if (!item) return 0;
    const gap = parseFloat(getComputedStyle(cabinetCarousel).gap || 0);
    return item.getBoundingClientRect().width + gap;
  };

  const setActiveDot = (index) => {
    cabinetDots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index);
    });
  };

  const wrapIndex = (index) => {
    const total = cabinetDots.length;
    return ((index % total) + total) % total;
  };

  const goToIndex = (index) => {
    const step = getStep();
    if (!step) return;
    const target = wrapIndex(index);
    cabinetCarousel.scrollTo({ left: step * target, behavior: 'smooth' });
    setActiveDot(target);
  };

  const getCurrentIndex = () => {
    const step = getStep();
    if (!step) return 0;
    return wrapIndex(Math.round(cabinetCarousel.scrollLeft / step));
  };

  cabinetPrev.addEventListener('click', () => {
    goToIndex(getCurrentIndex() - 1);
  });

  cabinetNext.addEventListener('click', () => {
    goToIndex(getCurrentIndex() + 1);
  });

  cabinetDots.forEach((dot, index) => {
    dot.addEventListener('click', () => goToIndex(index));
  });

  cabinetCarousel.addEventListener('scroll', () => {
    window.clearTimeout(scrollTimer);
    scrollTimer = window.setTimeout(() => {
      setActiveDot(getCurrentIndex());
    }, 60);
  });

  window.addEventListener('resize', () => {
    setActiveDot(getCurrentIndex());
  });

  setActiveDot(getCurrentIndex());
}

// ===== Carousel Nos valeurs =====
const valeursCarousel = document.getElementById('valeursCarousel');
const valeursPrev = document.querySelector('.valeurs-carousel-btn.prev');
const valeursNext = document.querySelector('.valeurs-carousel-btn.next');
const valeursDots = document.querySelectorAll('.valeurs-dot');

if (valeursCarousel && valeursPrev && valeursNext && valeursDots.length) {
  let vScrollTimer;

  const getVStep = () => {
    const item = valeursCarousel.querySelector('.valeur');
    if (!item) return 0;
    const gap = parseFloat(getComputedStyle(valeursCarousel).gap || 0);
    return item.getBoundingClientRect().width + gap;
  };

  const setVActiveDot = (index) => {
    valeursDots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index);
    });
  };

  const wrapVIndex = (index) => {
    const total = valeursDots.length;
    return ((index % total) + total) % total;
  };

  const goToVIndex = (index) => {
    const step = getVStep();
    if (!step) return;
    const target = wrapVIndex(index);
    valeursCarousel.scrollTo({ left: step * target, behavior: 'smooth' });
    setVActiveDot(target);
  };

  const getCurrentVIndex = () => {
    const step = getVStep();
    if (!step) return 0;
    return wrapVIndex(Math.round(valeursCarousel.scrollLeft / step));
  };

  valeursPrev.addEventListener('click', () => {
    goToVIndex(getCurrentVIndex() - 1);
  });

  valeursNext.addEventListener('click', () => {
    goToVIndex(getCurrentVIndex() + 1);
  });

  valeursDots.forEach((dot, index) => {
    dot.addEventListener('click', () => goToVIndex(index));
  });

  valeursCarousel.addEventListener('scroll', () => {
    window.clearTimeout(vScrollTimer);
    vScrollTimer = window.setTimeout(() => {
      setVActiveDot(getCurrentVIndex());
    }, 60);
  });

  window.addEventListener('resize', () => {
    setVActiveDot(getCurrentVIndex());
  });

  setVActiveDot(getCurrentVIndex());
}

// ===== Carousel Savoir-faire =====
const secteursCarousel = document.getElementById('secteursCarousel');
const secteursPrev = document.querySelector('.secteurs-carousel-btn.prev');
const secteursNext = document.querySelector('.secteurs-carousel-btn.next');
const secteursDots = document.querySelectorAll('.secteurs-dot');

if (secteursCarousel && secteursPrev && secteursNext && secteursDots.length) {
  let sScrollTimer;

  const getSStep = () => {
    const item = secteursCarousel.querySelector('.secteur');
    if (!item) return 0;
    const gap = parseFloat(getComputedStyle(secteursCarousel).gap || 0);
    return item.getBoundingClientRect().width + gap;
  };

  const setSActiveDot = (index) => {
    secteursDots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index);
    });
  };

  const wrapSIndex = (index) => {
    const total = secteursDots.length;
    return ((index % total) + total) % total;
  };

  const goToSIndex = (index) => {
    const step = getSStep();
    if (!step) return;
    const target = wrapSIndex(index);
    secteursCarousel.scrollTo({ left: step * target, behavior: 'smooth' });
    setSActiveDot(target);
  };

  const getCurrentSIndex = () => {
    const step = getSStep();
    if (!step) return 0;
    return wrapSIndex(Math.round(secteursCarousel.scrollLeft / step));
  };

  secteursPrev.addEventListener('click', () => {
    goToSIndex(getCurrentSIndex() - 1);
  });

  secteursNext.addEventListener('click', () => {
    goToSIndex(getCurrentSIndex() + 1);
  });

  secteursDots.forEach((dot, index) => {
    dot.addEventListener('click', () => goToSIndex(index));
  });

  secteursCarousel.addEventListener('scroll', () => {
    window.clearTimeout(sScrollTimer);
    sScrollTimer = window.setTimeout(() => {
      setSActiveDot(getCurrentSIndex());
    }, 60);
  });

  window.addEventListener('resize', () => {
    setSActiveDot(getCurrentSIndex());
  });

  setSActiveDot(getCurrentSIndex());
}