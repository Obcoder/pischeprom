/* Progressive enhancement only: the guide and every product link work without JS. */
(() => {
  'use strict';
  const navLinks = [...document.querySelectorAll('.section-nav a')];
  const sections = navLinks.map(link => document.querySelector(link.getAttribute('href'))).filter(Boolean);
  const markActive = id => navLinks.forEach(link => {
    const active = link.getAttribute('href') === '#' + id;
    link.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'location');
    else link.removeAttribute('aria-current');
  });
  let scheduled = false;
  const updateNavigation = () => {
    scheduled = false;
    let current = sections[0];
    for (const section of sections) if (section.getBoundingClientRect().top <= 140) current = section;
    if (current) markActive(current.id);
  };
  window.addEventListener('scroll', () => {
    if (!scheduled) { scheduled = true; window.requestAnimationFrame(updateNavigation); }
  }, { passive: true });
  updateNavigation();
  // Reveal an accordion before navigating to its contents, including source citations.
  const revealHash = hash => {
    if (!hash || hash === '#') return;
    let target;
    try { target = document.getElementById(decodeURIComponent(hash.slice(1))); } catch { return; }
    if (!target) return;
    if (target.tagName === 'DETAILS') target.open = true;
    let ancestor = target.parentElement;
    while (ancestor) { if (ancestor.tagName === 'DETAILS') ancestor.open = true; ancestor = ancestor.parentElement; }
  };
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (link) revealHash(link.getAttribute('href'));
  });
  window.addEventListener('hashchange', () => revealHash(location.hash));
  revealHash(location.hash);
})();
