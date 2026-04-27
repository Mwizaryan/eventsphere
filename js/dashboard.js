/**
 * js/dashboard.js
 * Fetches services from backend/get_services.php and renders them.
 * Handles category filtering, live search, and the Book Now modal.
 */

(() => {
  'use strict';

  // ─── STATE ───────────────────────────────────────────────────
  let allServices     = [];
  let activeFilter    = 'all';
  let currentBookSvcId = null;

  // ─── DOM REFS ────────────────────────────────────────────────
  const grid          = document.getElementById('servicesGrid');
  const stateLoading  = document.getElementById('dashLoading');
  const stateEmpty    = document.getElementById('dashEmpty');
  const stateError    = document.getElementById('dashError');
  const errorMsg      = document.getElementById('dashErrorMsg');
  const retryBtn      = document.getElementById('retryBtn');
  const searchInput   = document.getElementById('searchInput');
  const dashSubtitle  = document.getElementById('dashSubtitle');
  const userGreeting  = document.getElementById('userGreeting');
  const logoutBtn     = document.getElementById('logoutBtn');
  const filterBtns    = document.querySelectorAll('.sidebar-item');

  // Booking modal
  const bookingOverlay = document.getElementById('bookingOverlay');
  const bookingModal   = document.getElementById('bookingModal');
  const bookingClose   = document.getElementById('bookingCloseBtn');
  const bookModalTitle = document.getElementById('bookModalTitle');
  const bookModalSub   = document.getElementById('bookModalSubtitle');
  const bookingForm    = document.getElementById('bookingForm');
  const bookingFeedback= document.getElementById('bookingFeedback');
  const eventDateInput = document.getElementById('eventDate');
  const serviceIdInput = document.getElementById('bookingServiceId');
  const bookSubmitBtn  = document.getElementById('bookSubmitBtn');

  // ─── INIT ────────────────────────────────────────────────────
  init();

  async function init() {
    await loadUserInfo();
    await loadServices();
    attachEventListeners();
  }

  // ─── USER INFO ───────────────────────────────────────────────
  async function loadUserInfo() {
    try {
      const res  = await fetch('backend/check_auth.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (data.logged_in && data.user?.name) {
        userGreeting.textContent = `Welcome back, ${data.user.name.split(' ')[0]} 👋`;
        
        // Show/hide admin nav links based on is_admin status
        const adminNavLinks = document.getElementById('adminNavLinks');
        if (adminNavLinks) {
          if (data.user.is_admin) {
            adminNavLinks.classList.remove('hidden');
            adminNavLinks.style.display = 'flex';
            localStorage.setItem('eventsphere_is_admin', 'true');
          } else {
            adminNavLinks.classList.add('hidden');
            adminNavLinks.style.display = 'none';
            localStorage.removeItem('eventsphere_is_admin');
          }
        }
      }
    } catch { /* non-critical */ }
  }

  // ─── SERVICES API ────────────────────────────────────────────
  async function loadServices() {
    showState('loading');
    try {
      const res  = await fetch('backend/get_services.php', { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'API error');

      allServices = data.services;
      renderServices();
    } catch (err) {
      errorMsg.textContent = err.message || 'Could not load services. Please try again.';
      showState('error');
    }
  }

  // ─── RENDER ──────────────────────────────────────────────────
  function renderServices() {
    const query    = searchInput.value.trim().toLowerCase();
    const filtered = allServices.filter(svc => {
      const matchCat    = activeFilter === 'all' || svc.category === activeFilter;
      const matchSearch = !query
        || svc.title.toLowerCase().includes(query)
        || (svc.description || '').toLowerCase().includes(query);
      return matchCat && matchSearch;
    });

    updateSubtitle(filtered.length);

    if (filtered.length === 0) {
      showState('empty');
      return;
    }

    showState('grid');
    grid.innerHTML = '';

    filtered.forEach((svc, i) => {
      const card = buildCard(svc, i);
      grid.appendChild(card);
    });
  }

  function buildCard(svc, index) {
    const categoryMeta = {
      venue:       { label: 'Venue',       icon: '🏛️', cls: 'cat-venue' },
      entertainer: { label: 'Entertainer', icon: '🎤', cls: 'cat-entertainer' },
      catering:    { label: 'Catering',    icon: '🍽️', cls: 'cat-catering' },
    };
    const meta    = categoryMeta[svc.category] || { label: svc.category, icon: '✦', cls: '' };
    const price   = parseFloat(svc.price).toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
    const desc    = svc.description || 'No description available.';

    const article = document.createElement('article');
    article.className = 'svc-card';
    article.setAttribute('role', 'listitem');
    article.style.animationDelay = `${index * 0.05}s`;

    // Image or emoji placeholder
    const imgHtml = svc.image_url
      ? `<img src="${escHtml(svc.image_url)}" alt="${escHtml(svc.title)}" class="svc-card-img" loading="lazy" />`
      : `<div class="svc-card-img placeholder" aria-hidden="true">${meta.icon}</div>`;

    article.innerHTML = `
      ${imgHtml}
      <div class="svc-card-body">
        <span class="svc-card-category ${meta.cls}">${meta.label}</span>
        <h3 class="svc-card-title">${escHtml(svc.title)}</h3>
        <p class="svc-card-desc">${escHtml(desc)}</p>
        <div class="svc-card-footer">
          <span class="svc-card-price">${price}</span>
          <button
            class="btn btn-primary"
            data-id="${svc.id}"
            data-title="${escHtml(svc.title)}"
            data-price="${price}"
            aria-label="Book ${escHtml(svc.title)}"
          >Book Now</button>
        </div>
      </div>
    `;

    // Attach book button listener
    article.querySelector('[data-id]').addEventListener('click', openBookingModal);

    return article;
  }

  // ─── FILTER HANDLING ─────────────────────────────────────────
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      activeFilter = btn.dataset.filter;
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderServices();
    });
  });

  // ─── SEARCH ──────────────────────────────────────────────────
  let searchTimer;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(renderServices, 220);
  });

  // ─── SUBTITLE ────────────────────────────────────────────────
  function updateSubtitle(count) {
    const catLabel = activeFilter === 'all' ? 'services' : `${activeFilter}s`;
    dashSubtitle.textContent = `Showing ${count} ${catLabel}`;
  }

  // ─── STATE DISPLAY ───────────────────────────────────────────
  function showState(state) {
    stateLoading.classList.toggle('hidden', state !== 'loading');
    stateEmpty.classList.toggle('hidden',   state !== 'empty');
    stateError.classList.toggle('hidden',   state !== 'error');
    grid.classList.toggle('hidden',         state !== 'grid');
  }

  // Retry
  retryBtn.addEventListener('click', loadServices);

  // ─── BOOKING MODAL ───────────────────────────────────────────
  function openBookingModal(e) {
    const btn    = e.currentTarget;
    const svcId  = btn.dataset.id;
    const title  = btn.dataset.title;
    const price  = btn.dataset.price;

    currentBookSvcId = svcId;
    serviceIdInput.value = svcId;
    bookModalTitle.textContent = title;
    bookModalSub.textContent   = `Rate: ${price}`;

    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    eventDateInput.min   = tomorrow.toISOString().split('T')[0];
    eventDateInput.value = '';

    clearBookingFeedback();
    bookingOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => eventDateInput.focus(), 100);
  }

  function closeBookingModal() {
    bookingOverlay.classList.remove('active');
    document.body.style.overflow = '';
    bookingForm.reset();
    clearBookingFeedback();
  }

  bookingClose.addEventListener('click', closeBookingModal);
  bookingOverlay.addEventListener('click', (e) => { if (e.target === bookingOverlay) closeBookingModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && bookingOverlay.classList.contains('active')) closeBookingModal();
  });

  // ─── BOOKING SUBMIT ──────────────────────────────────────────
  bookingForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearBookingFeedback();

    const eventDate = eventDateInput.value;
    if (!eventDate) {
      showBookingFeedback('Please select an event date.', 'error');
      return;
    }

    setBookingLoading(true);

    try {
      const res  = await fetch('backend/book_service.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({ service_id: currentBookSvcId, event_date: eventDate }),
      });
      const data = await res.json();

      if (res.ok && data.success) {
        closeBookingModal();
        // Format the date nicely for the toast
        const formatted = new Date(eventDate + 'T00:00:00').toLocaleDateString('en-US', {
          weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        showSuccessToast(data.service || bookModalTitle.textContent, formatted);
      } else {
        showBookingFeedback(data.message || 'Booking failed. Please try again.', 'error');
      }
    } catch {
      showBookingFeedback('Could not reach the server. Please try again.', 'error');
    } finally {
      setBookingLoading(false);
    }
  });

  function setBookingLoading(on) {
    const text    = bookSubmitBtn.querySelector('.btn-text');
    const spinner = bookSubmitBtn.querySelector('.btn-spinner');
    bookSubmitBtn.disabled = on;
    text.hidden    = on;
    spinner.hidden = !on;
  }

  function showBookingFeedback(msg, type) {
    bookingFeedback.textContent = msg;
    bookingFeedback.className   = `modal-feedback ${type}`;
  }
  function clearBookingFeedback() {
    bookingFeedback.textContent = '';
    bookingFeedback.className   = 'modal-feedback';
  }

  // ─── SUCCESS TOAST ───────────────────────────────────────────
  function showSuccessToast(serviceTitle, eventDateFormatted) {
    // Remove any existing toast
    document.getElementById('successToast')?.remove();

    const toast = document.createElement('div');
    toast.id    = 'successToast';
    toast.className = 'success-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    // Build confetti dots
    const dots = Array.from({ length: 12 }, (_, i) => {
      const colors = ['#7c6af7','#c26ef7','#f0a500','#10b981','#f472b6','#60a5fa'];
      const size   = 6 + Math.random() * 8;
      return `<span class="confetti-dot" style="
        left:${10 + Math.random() * 80}%;
        background:${colors[i % colors.length]};
        width:${size}px; height:${size}px;
        animation-delay:${Math.random() * 0.4}s;
        animation-duration:${0.6 + Math.random() * 0.5}s;
      "></span>`;
    }).join('');

    toast.innerHTML = `
      ${dots}
      <div class="toast-inner">
        <div class="toast-check">
          <svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="check-circle" cx="26" cy="26" r="24" stroke="white" stroke-width="3"/>
            <path  class="check-tick"   d="M14 26l8 8 16-16" stroke="white" stroke-width="3.5"
                   stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p class="toast-headline">Successfully Booked! 🎉</p>
        <p class="toast-service">${escHtml(serviceTitle)}</p>
        <p class="toast-date">${escHtml(eventDateFormatted)}</p>
        <p class="toast-status">Status: <strong>Pending Confirmation</strong></p>
        <button class="btn btn-primary toast-close-btn" id="toastCloseBtn">
          View My Bookings
        </button>
      </div>
    `;

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    document.getElementById('toastCloseBtn').addEventListener('click', () => {
      window.location.href = 'my_events.html';
    });

    // Auto-redirect after 3 seconds
    setTimeout(() => {
      if (document.getElementById('successToast')) {
        window.location.href = 'my_events.html';
      }
    }, 3000);
  }

  function dismissToast() {
    const toast = document.getElementById('successToast');
    if (!toast) return;
    toast.classList.remove('toast-visible');
    toast.classList.add('toast-hiding');
    toast.addEventListener('animationend', () => {
      toast.remove();
      window.location.href = 'my_events.html';
    }, { once: true });
  }

  // ─── LOGOUT ──────────────────────────────────────────────────
  logoutBtn.addEventListener('click', async () => {
    try {
      await fetch('backend/logout.php', { credentials: 'same-origin' });
    } finally {
      window.location.replace('index.html');
    }
  });

  // ─── HELPERS ─────────────────────────────────────────────────
  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

})();
