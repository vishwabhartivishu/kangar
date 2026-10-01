// Kangar Dubai Experts — light interactions

// Mobile nav toggle
const navToggle = document.querySelector('.nav-toggle');
const navLinks = document.querySelector('.nav-links');
if (navToggle && navLinks) {
  navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
}

// Filter chips (property listing)
document.querySelectorAll('.filter-row').forEach(row => {
  row.addEventListener('click', e => {
    const chip = e.target.closest('.filter-chip');
    if (!chip) return;
    row.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    const filter = chip.dataset.filter;
    const grid = document.querySelector('.property-grid');
    if (!grid) return;
    grid.querySelectorAll('.property-card').forEach(card => {
      const tags = (card.dataset.tags || '').split(',');
      card.style.display = (filter === 'all' || tags.includes(filter)) ? '' : 'none';
    });
  });
});

// Reveal on scroll
const io = new IntersectionObserver((entries) => {
  entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }});
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));

// Form fake submit
document.querySelectorAll('form[data-fake]').forEach(f => {
  f.addEventListener('submit', e => {
    e.preventDefault();
    const s = f.querySelector('.form-success');
    if (s) s.classList.add('show');
    f.reset();
    setTimeout(() => s && s.classList.remove('show'), 6000);
  });
});

// Active nav link
const path = location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('.nav-links a').forEach(a => {
  if (a.getAttribute('href') === path) a.classList.add('active');
});

// Make property cards fully clickable (uses the card's View link as target)
document.querySelectorAll('.pr-card').forEach(card => {
  const link = card.querySelector('a.pr-card-view, a[href*="property-detail"]');
  if (!link) return;
  card.style.cursor = 'pointer';
  card.addEventListener('click', e => {
    if (e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
      window.open(link.href, '_blank', 'noopener');
    } else {
      window.location.href = link.href;
    }
  });
  card.setAttribute('tabindex', '0');
  card.setAttribute('role', 'link');
  card.addEventListener('keydown', e => {
    if (e.key === 'Enter') { window.location.href = link.href; }
  });
});

// UAF hero search — sync visible value with native select
document.querySelectorAll('.uaf-search-native').forEach(sel => {
  const field = sel.closest('.uaf-search-field');
  const valueEl = field && field.querySelector('[data-value]');
  if (!valueEl) return;
  sel.addEventListener('change', () => { valueEl.textContent = sel.value; });
});
