/**
 * js/my_events.js
 * Fetches user's bookings from backend/get_my_bookings.php and renders them.
 */

(() => {
  'use strict';

  // ─── DOM REFS ────────────────────────────────────────────────
  const list          = document.getElementById('bookingsList');
  const stateLoading  = document.getElementById('eventsLoading');
  const stateEmpty    = document.getElementById('eventsEmpty');
  const stateError    = document.getElementById('eventsError');
  const retryBtn      = document.getElementById('retryEventsBtn');
  const logoutBtn     = document.getElementById('logoutBtn');

  // ─── INIT ────────────────────────────────────────────────────
  init();

  async function init() {
    await loadBookings();
    attachEventListeners();
  }

  // ─── LOAD BOOKINGS ───────────────────────────────────────────
  async function loadBookings() {
    showState('loading');
    try {
      const res  = await fetch('backend/get_my_bookings.php', { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'API error');

      renderBookings(data.bookings);
    } catch (err) {
      showState('error');
    }
  }

  // ─── RENDER ──────────────────────────────────────────────────
  function renderBookings(bookings) {
    if (bookings.length === 0) {
      showState('empty');
      return;
    }

    showState('list');
    list.innerHTML = '';

    bookings.forEach((booking, i) => {
      const item = buildBookingItem(booking, i);
      list.appendChild(item);
    });
  }

  function buildBookingItem(booking, index) {
    const categoryMeta = {
      venue:       { icon: '🏛️', cls: 'cat-venue' },
      entertainer: { icon: '🎤', cls: 'cat-entertainer' },
      catering:    { icon: '🍽️', cls: 'cat-catering' },
    };
    const meta    = categoryMeta[booking.category] || { icon: '✦', cls: '' };
    const dateObj = new Date(booking.event_date + 'T00:00:00');
    const formattedDate = dateObj.toLocaleDateString('en-US', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    const price   = parseFloat(booking.price).toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

    const card = document.createElement('div');
    card.className = 'booking-card';
    card.style.animationDelay = `${index * 0.05}s`;

    card.innerHTML = `
      <div class="booking-card-main">
        <div class="booking-img-wrap">
          ${booking.image_url 
             ? `<img src="${escHtml(booking.image_url)}" alt="${escHtml(booking.title)}" />` 
             : `<div class="booking-img-placeholder">${meta.icon}</div>`}
        </div>
        <div class="booking-info">
          <span class="svc-card-category ${meta.cls}">${booking.category}</span>
          <h3 class="booking-title">${escHtml(booking.title)}</h3>
          <p class="booking-date">📅 ${formattedDate}</p>
          <p class="booking-price">Price: ${price}</p>
        </div>
      </div>
      <div class="booking-status-wrap">
        <span class="status-badge status-${booking.status}">${booking.status.toUpperCase()}</span>
      </div>
    `;

    return card;
  }

  // ─── STATE DISPLAY ───────────────────────────────────────────
  function showState(state) {
    stateLoading.classList.toggle('hidden', state !== 'loading');
    stateEmpty.classList.toggle('hidden',   state !== 'empty');
    stateError.classList.toggle('hidden',   state !== 'error');
    list.classList.toggle('hidden',         state !== 'list');
  }

  function attachEventListeners() {
    retryBtn?.addEventListener('click', loadBookings);
    
    logoutBtn?.addEventListener('click', async () => {
      try {
        await fetch('backend/logout.php', { credentials: 'same-origin' });
      } finally {
        window.location.replace('index.html');
      }
    });
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

})();
