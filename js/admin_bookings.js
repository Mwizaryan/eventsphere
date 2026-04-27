/**
 * js/admin_bookings.js
 * Fetches all bookings and handles status updates for the admin panel.
 */

(() => {
  'use strict';

  // ─── ADMIN GUARD ──────────────────────────────────────────────
  (async () => {
    try {
      const res  = await fetch('backend/check_auth.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.logged_in || !data.user?.is_admin) {
        window.location.replace('dashboard.html');
      }
    } catch {
      window.location.replace('index.html');
    }
  })();

  // ─── DOM REFS ────────────────────────────────────────────────
  const tableArea     = document.getElementById('bookingsArea');
  const tbody         = document.getElementById('bookingsBody');
  const stateLoading  = document.getElementById('adminLoading');
  const stateEmpty    = document.getElementById('adminEmpty');
  const stateError    = document.getElementById('adminError');
  const retryBtn      = document.getElementById('retryAdminBtn');
  const logoutBtn     = document.getElementById('logoutBtn');

  // ─── INIT ────────────────────────────────────────────────────
  init();

  async function init() {
    await loadAllBookings();
    attachEventListeners();
  }

  // ─── DATA LOADING ────────────────────────────────────────────
  async function loadAllBookings() {
    showState('loading');
    try {
      const res  = await fetch('backend/get_all_bookings.php', { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'API error');

      renderBookings(data.bookings);
    } catch (err) {
      console.error(err);
      showState('error');
    }
  }

  // ─── RENDER ──────────────────────────────────────────────────
  function renderBookings(bookings) {
    if (bookings.length === 0) {
      showState('empty');
      return;
    }

    showState('table');
    tbody.innerHTML = '';

    bookings.forEach((b, i) => {
      const tr = document.createElement('tr');
      tr.className = 'admin-tr';
      tr.style.animationDelay = `${i * 0.03}s`;

      const dateStr = new Date(b.event_date + 'T00:00:00').toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric'
      });
      const priceStr = parseFloat(b.service_price).toLocaleString('en-US', { 
        style: 'currency', currency: 'USD', maximumFractionDigits: 0 
      });

      tr.innerHTML = `
        <td><small class="text-muted">#${b.id}</small></td>
        <td>
          <div class="cell-user">
            <span class="user-name">${escHtml(b.customer_name)}</span>
            <span class="user-email">${escHtml(b.customer_email)}</span>
          </div>
        </td>
        <td>
          <div class="cell-svc">
            <span class="svc-name">${escHtml(b.service_title)}</span>
            <span class="svc-cat text-muted text-xs uppercase">${b.service_category}</span>
          </div>
        </td>
        <td>${dateStr}</td>
        <td class="font-bold">${priceStr}</td>
        <td>
          <span class="status-badge status-${b.status}" id="status-badge-${b.id}">${b.status.toUpperCase()}</span>
        </td>
        <td id="actions-${b.id}">
          ${b.status === 'pending' ? `
            <div class="admin-actions">
              <button class="btn btn-success btn-sm btn-icon-only" onclick="updateBookingStatus(${b.id}, 'confirmed')" title="Confirm Booking">
                ✓
              </button>
              <button class="btn btn-error btn-sm btn-icon-only" onclick="updateBookingStatus(${b.id}, 'cancelled')" title="Cancel/Reject Booking">
                ✕
              </button>
            </div>
          ` : `<small class="text-muted">No Actions</small>`}
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  // ─── STATUS UPDATING ─────────────────────────────────────────
  window.updateBookingStatus = async (bookingId, newStatus) => {
    const actionsCell = document.getElementById(`actions-${bookingId}`);
    const badge       = document.getElementById(`status-badge-${bookingId}`);
    const originalContent = actionsCell.innerHTML;

    // Loading state
    actionsCell.innerHTML = '<div class="spinner-ring mini"></div>';

    try {
      const res  = await fetch('backend/update_status.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({ booking_id: bookingId, new_status: newStatus }),
      });
      const data = await res.json();

      if (res.ok && data.success) {
        // Success: Update UI
        badge.className = `status-badge status-${newStatus}`;
        badge.textContent = newStatus.toUpperCase();
        actionsCell.innerHTML = `<small class="text-muted">Completed</small>`;
      } else {
        alert(data.message || 'Failed to update status.');
        actionsCell.innerHTML = originalContent; // Reset
      }
    } catch {
      alert('Could not reach the server.');
      actionsCell.innerHTML = originalContent; // Reset
    }
  };

  // ─── UI HELPERS ──────────────────────────────────────────────
  function showState(state) {
    stateLoading.classList.toggle('hidden', state !== 'loading');
    stateEmpty.classList.toggle('hidden',   state !== 'empty');
    stateError.classList.toggle('hidden',   state !== 'error');
    tableArea.classList.toggle('hidden',    state !== 'table');
  }

  function attachEventListeners() {
    retryBtn?.addEventListener('click', loadAllBookings);
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
