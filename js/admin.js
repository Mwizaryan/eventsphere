/**
 * js/admin.js
 * Handles the admin "Add Service" form:
 * - Visual category picker
 * - Live card preview
 * - Image URL preview
 * - Validation + fetch() to backend/add_service.php
 * - Recently added list in the preview panel
 */

(() => {
  'use strict';

  // ─── DOM REFS ────────────────────────────────────────────────
  const form          = document.getElementById('addServiceForm');
  const feedback      = document.getElementById('adminFeedback');
  const submitBtn     = document.getElementById('submitBtn');
  const resetBtn      = document.getElementById('resetBtn');

  // Category picker
  const catBtns       = document.querySelectorAll('.cat-btn');
  const categoryInput = document.getElementById('categoryValue');
  const catError      = document.getElementById('catError');

  // Form fields
  const titleInput    = document.getElementById('serviceTitle');
  const descInput     = document.getElementById('serviceDesc');
  const priceInput    = document.getElementById('servicePrice');
  const imageInput    = document.getElementById('serviceImage');

  // Image preview
  const imgPreviewWrap = document.getElementById('imgPreviewWrap');
  const imgPreview     = document.getElementById('imgPreview');

  // Live preview card
  const previewCard    = document.getElementById('previewCard');
  const previewImg     = document.getElementById('previewImg');
  const previewCat     = document.getElementById('previewCat');
  const previewTitle   = document.getElementById('previewTitle');
  const previewDesc    = document.getElementById('previewDesc');
  const previewPrice   = document.getElementById('previewPrice');

  // Recent list
  const recentList     = document.getElementById('recentList');
  const recentWrap     = document.getElementById('recentWrap');

  // ─── CATEGORY META ───────────────────────────────────────────
  const categoryMeta = {
    venue:       { label: 'Venue',       icon: '🏛️', cls: 'cat-venue' },
    entertainer: { label: 'Entertainer', icon: '🎤', cls: 'cat-entertainer' },
    catering:    { label: 'Catering',    icon: '🍽️', cls: 'cat-catering' },
  };

  // ─── CATEGORY PICKER ─────────────────────────────────────────
  catBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const val = btn.dataset.value;
      catBtns.forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      categoryInput.value = val;
      catError.classList.add('hidden');

      // Update preview
      const meta = categoryMeta[val];
      previewCat.textContent  = meta.label;
      previewCat.className    = `svc-card-category preview-cat ${meta.cls}`;
      if (!imageInput.value) setPreviewPlaceholder(meta.icon);
    });
  });

  // ─── LIVE PREVIEW UPDATES ────────────────────────────────────
  titleInput.addEventListener('input', () => {
    previewTitle.textContent = titleInput.value.trim() || 'Service title will appear here';
  });

  descInput.addEventListener('input', () => {
    previewDesc.textContent = descInput.value.trim() || 'Description preview will appear here as you type…';
  });

  priceInput.addEventListener('input', () => {
    const val = parseFloat(priceInput.value);
    previewPrice.textContent = !isNaN(val)
      ? val.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
      : '$0.00';
  });

  // ─── IMAGE URL PREVIEW ───────────────────────────────────────
  let imgTimer;
  imageInput.addEventListener('input', () => {
    clearTimeout(imgTimer);
    const url = imageInput.value.trim();
    if (!url) {
      imgPreviewWrap.classList.add('hidden');
      setPreviewPlaceholder(categoryMeta[categoryInput.value]?.icon || '✦');
      return;
    }
    imgTimer = setTimeout(() => {
      const testImg = new Image();
      testImg.onload = () => {
        imgPreview.src = url;
        imgPreviewWrap.classList.remove('hidden');
        // Update preview card image
        previewImg.innerHTML = '';
        previewImg.style.backgroundImage = `url('${url}')`;
        previewImg.style.backgroundSize  = 'cover';
        previewImg.style.backgroundPosition = 'center';
      };
      testImg.onerror = () => {
        imgPreviewWrap.classList.add('hidden');
        setPreviewPlaceholder(categoryMeta[categoryInput.value]?.icon || '✦');
      };
      testImg.src = url;
    }, 600);
  });

  function setPreviewPlaceholder(icon) {
    previewImg.style.backgroundImage   = '';
    previewImg.style.backgroundSize    = '';
    previewImg.style.backgroundPosition = '';
    previewImg.textContent = icon;
  }

  // ─── VALIDATION ──────────────────────────────────────────────
  function validate() {
    let valid = true;

    // Category
    if (!categoryInput.value) {
      catError.classList.remove('hidden');
      valid = false;
    } else {
      catError.classList.add('hidden');
    }

    // Title
    if (!titleInput.value.trim()) {
      titleInput.classList.add('invalid');
      valid = false;
    } else {
      titleInput.classList.remove('invalid');
    }

    // Price
    const price = parseFloat(priceInput.value);
    if (isNaN(price) || price < 0) {
      priceInput.classList.add('invalid');
      valid = false;
    } else {
      priceInput.classList.remove('invalid');
    }

    return valid;
  }

  // ─── FORM SUBMIT ─────────────────────────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFeedback();

    if (!validate()) {
      showFeedback('Please fix the highlighted fields before submitting.', 'error');
      return;
    }

    const payload = {
      category:    categoryInput.value,
      title:       titleInput.value.trim(),
      description: descInput.value.trim(),
      price:       parseFloat(priceInput.value),
      image_url:   imageInput.value.trim() || '',
    };

    setLoading(true);

    try {
      const res  = await fetch('backend/add_service.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
      });
      const data = await res.json();

      if (res.ok && data.success) {
        showFeedback(`✓ "${payload.title}" was added successfully! (ID: ${data.service_id})`, 'success');
        addToRecentList(payload, data.service_id);
        resetForm();
      } else {
        showFeedback(data.message || 'Failed to add service. Please try again.', 'error');
      }
    } catch {
      showFeedback('Could not reach the server. Is XAMPP running?', 'error');
    } finally {
      setLoading(false);
    }
  });

  // ─── RESET ───────────────────────────────────────────────────
  resetBtn.addEventListener('click', resetForm);

  function resetForm() {
    form.reset();
    catBtns.forEach(b => b.classList.remove('selected'));
    categoryInput.value = '';
    [titleInput, priceInput, descInput, imageInput].forEach(el => el.classList.remove('invalid'));
    imgPreviewWrap.classList.add('hidden');
    catError.classList.add('hidden');

    // Reset preview
    previewCat.textContent    = 'Category';
    previewCat.className      = 'svc-card-category preview-cat';
    previewTitle.textContent  = 'Service title will appear here';
    previewDesc.textContent   = 'Description preview will appear here as you type…';
    previewPrice.textContent  = '$0.00';
    setPreviewPlaceholder('✦');
  }

  // ─── RECENTLY ADDED ──────────────────────────────────────────
  function addToRecentList(svc, id) {
    recentWrap.classList.remove('hidden');
    const meta = categoryMeta[svc.category] || { icon: '✦' };
    const price = svc.price.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

    const li = document.createElement('li');
    li.className = 'recent-item';
    li.innerHTML = `
      <span class="recent-item-icon">${meta.icon}</span>
      <div class="recent-item-info">
        <div class="recent-item-title">${escHtml(svc.title)}</div>
        <div class="recent-item-price">${price} · ID #${id}</div>
      </div>
    `;
    recentList.prepend(li);

    // Cap list at 5 items
    while (recentList.children.length > 5) {
      recentList.removeChild(recentList.lastChild);
    }
  }

  // ─── UI HELPERS ──────────────────────────────────────────────
  function setLoading(on) {
    const text    = submitBtn.querySelector('.btn-text');
    const spinner = submitBtn.querySelector('.btn-spinner');
    submitBtn.disabled = on;
    text.hidden    = on;
    spinner.hidden = !on;
  }

  function showFeedback(msg, type) {
    feedback.textContent = msg;
    feedback.className   = `admin-feedback ${type}`;
    feedback.classList.remove('hidden');
    feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearFeedback() {
    feedback.textContent = '';
    feedback.className   = 'admin-feedback hidden';
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

})();
