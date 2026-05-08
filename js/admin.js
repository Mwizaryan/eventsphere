/**
 * js/admin.js
 * Handles admin add/edit/delete service workflows and the service list UI.
 */

(() => {
  'use strict';

  (async () => {
    try {
      const res = await fetch('backend/check_auth.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.logged_in || !data.user?.is_admin) {
        window.location.replace('dashboard.html');
      }
    } catch {
      window.location.replace('index.html');
    }
  })();

  const form = document.getElementById('addServiceForm');
  const feedback = document.getElementById('adminFeedback');
  const submitBtn = document.getElementById('submitBtn');
  const resetBtn = document.getElementById('resetBtn');
  const cancelEditBtn = document.getElementById('cancelEditBtn');
  const logoutBtn = document.getElementById('logoutBtn');

  const catBtns = document.querySelectorAll('.cat-btn');
  const categoryInput = document.getElementById('categoryValue');
  const catError = document.getElementById('catError');
  const serviceIdInput = document.getElementById('serviceId');

  const titleInput = document.getElementById('serviceTitle');
  const descInput = document.getElementById('serviceDesc');
  const priceInput = document.getElementById('servicePrice');
  const imageInput = document.getElementById('serviceImage');

  const imgPreviewWrap = document.getElementById('imgPreviewWrap');
  const imgPreview = document.getElementById('imgPreview');

  const previewImg = document.getElementById('previewImg');
  const previewCat = document.getElementById('previewCat');
  const previewTitle = document.getElementById('previewTitle');
  const previewDesc = document.getElementById('previewDesc');
  const previewPrice = document.getElementById('previewPrice');

  const recentList = document.getElementById('recentList');
  const recentWrap = document.getElementById('recentWrap');

  const servicesLoading = document.getElementById('servicesLoading');
  const servicesEmpty = document.getElementById('servicesEmpty');
  const servicesError = document.getElementById('servicesError');
  const servicesErrorMsg = document.getElementById('servicesErrorMsg');
  const servicesTableWrap = document.getElementById('servicesTableWrap');
  const servicesTableBody = document.getElementById('servicesTableBody');
  const refreshServicesBtn = document.getElementById('refreshServicesBtn');

  const categoryMeta = {
    venue:       { label: 'Venue',       icon: 'ðŸ›ï¸', cls: 'cat-venue' },
    entertainer: { label: 'Entertainer', icon: 'ðŸŽ¤', cls: 'cat-entertainer' },
    catering:    { label: 'Catering',    icon: 'ðŸ½ï¸', cls: 'cat-catering' },
  };

  let currentMode = 'create';
  let servicesCache = [];

  init();

  async function init() {
    attachStaticEvents();
    await loadServicesList();
  }

  catBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const val = btn.dataset.value;
      catBtns.forEach((item) => item.classList.remove('selected'));
      btn.classList.add('selected');
      categoryInput.value = val;
      catError.classList.add('hidden');

      const meta = categoryMeta[val];
      previewCat.textContent = meta.label;
      previewCat.className = `svc-card-category preview-cat ${meta.cls}`;
      if (!imageInput.value) setPreviewPlaceholder(meta.icon);
    });
  });

  titleInput.addEventListener('input', () => {
    previewTitle.textContent = titleInput.value.trim() || 'Service title will appear here';
  });

  descInput.addEventListener('input', () => {
    previewDesc.textContent = descInput.value.trim() || 'Description preview will appear here as you typeâ€¦';
  });

  priceInput.addEventListener('input', () => {
    const val = parseFloat(priceInput.value);
    previewPrice.textContent = !isNaN(val)
      ? val.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
      : '$0.00';
  });

  let imgTimer;
  imageInput.addEventListener('input', () => {
    clearTimeout(imgTimer);
    const url = imageInput.value.trim();
    if (!url) {
      imgPreviewWrap.classList.add('hidden');
      setPreviewPlaceholder(categoryMeta[categoryInput.value]?.icon || 'âœ¦');
      return;
    }

    imgTimer = setTimeout(() => {
      const testImg = new Image();
      testImg.onload = () => {
        imgPreview.src = url;
        imgPreviewWrap.classList.remove('hidden');
        previewImg.innerHTML = '';
        previewImg.style.backgroundImage = `url('${url}')`;
        previewImg.style.backgroundSize = 'cover';
        previewImg.style.backgroundPosition = 'center';
      };
      testImg.onerror = () => {
        imgPreviewWrap.classList.add('hidden');
        setPreviewPlaceholder(categoryMeta[categoryInput.value]?.icon || 'âœ¦');
      };
      testImg.src = url;
    }, 600);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFeedback();

    if (!validate()) {
      showFeedback('Please fix the highlighted fields before submitting.', 'error');
      return;
    }

    const payload = {
      service_id: serviceIdInput.value ? parseInt(serviceIdInput.value, 10) : undefined,
      category: categoryInput.value,
      title: titleInput.value.trim(),
      description: descInput.value.trim(),
      price: parseFloat(priceInput.value),
      image_url: imageInput.value.trim() || '',
    };

    setLoading(true);

    try {
      const isEditing = currentMode === 'edit';
      const endpoint = isEditing ? 'backend/edit_service.php' : 'backend/add_service.php';
      const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (res.ok && data.success) {
        if (isEditing) {
          showFeedback(`"${payload.title}" was updated successfully.`, 'success');
        } else {
          showFeedback(`"${payload.title}" was added successfully! (ID: ${data.service_id})`, 'success');
          addToRecentList(payload, data.service_id);
        }

        resetForm();
        await loadServicesList();
      } else {
        showFeedback(data.message || 'Failed to save service. Please try again.', 'error');
      }
    } catch {
      showFeedback('Could not reach the server. Is XAMPP running?', 'error');
    } finally {
      setLoading(false);
    }
  });

  function attachStaticEvents() {
    resetBtn?.addEventListener('click', resetForm);
    cancelEditBtn?.addEventListener('click', resetForm);
    refreshServicesBtn?.addEventListener('click', loadServicesList);

    servicesTableBody?.addEventListener('click', (event) => {
      const editBtn = event.target.closest('[data-edit-service]');
      if (editBtn) {
        const serviceId = parseInt(editBtn.dataset.editService || '0', 10);
        const service = servicesCache.find((item) => Number(item.id) === serviceId);
        if (service) {
          populateFormForEdit(service);
        }
        return;
      }

      const deleteBtn = event.target.closest('[data-delete-service]');
      if (deleteBtn) {
        const serviceId = parseInt(deleteBtn.dataset.deleteService || '0', 10);
        if (serviceId) {
          deleteService(serviceId);
        }
      }
    });

    logoutBtn?.addEventListener('click', async () => {
      try {
        await fetch('backend/logout.php', { credentials: 'same-origin' });
      } finally {
        window.location.replace('index.html');
      }
    });
  }

  function validate() {
    let valid = true;

    if (!categoryInput.value) {
      catError.classList.remove('hidden');
      valid = false;
    } else {
      catError.classList.add('hidden');
    }

    if (!titleInput.value.trim()) {
      titleInput.classList.add('invalid');
      valid = false;
    } else {
      titleInput.classList.remove('invalid');
    }

    const price = parseFloat(priceInput.value);
    if (isNaN(price) || price < 0) {
      priceInput.classList.add('invalid');
      valid = false;
    } else {
      priceInput.classList.remove('invalid');
    }

    return valid;
  }

  function resetForm() {
    form.reset();
    currentMode = 'create';
    serviceIdInput.value = '';
    catBtns.forEach((btn) => btn.classList.remove('selected'));
    categoryInput.value = '';
    [titleInput, priceInput, descInput, imageInput].forEach((el) => el.classList.remove('invalid'));
    imgPreviewWrap.classList.add('hidden');
    catError.classList.add('hidden');
    cancelEditBtn?.classList.add('hidden');
    submitBtn.querySelector('.btn-text').textContent = 'âœ¦ Add Service';

    previewCat.textContent = 'Category';
    previewCat.className = 'svc-card-category preview-cat';
    previewTitle.textContent = 'Service title will appear here';
    previewDesc.textContent = 'Description preview will appear here as you typeâ€¦';
    previewPrice.textContent = '$0.00';
    setPreviewPlaceholder('âœ¦');
  }

  async function loadServicesList() {
    showServicesState('loading');
    try {
      const res = await fetch('backend/get_services.php', { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'API error');

      servicesCache = data.services || [];
      renderServicesTable(servicesCache);
    } catch (err) {
      servicesErrorMsg.textContent = err.message || 'Could not load services.';
      showServicesState('error');
    }
  }

  function renderServicesTable(services) {
    servicesTableBody.innerHTML = '';

    if (!services.length) {
      showServicesState('empty');
      return;
    }

    showServicesState('table');

    services.forEach((svc) => {
      const row = document.createElement('tr');
      const price = parseFloat(svc.price).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
      });

      row.innerHTML = `
        <td>#${svc.id}</td>
        <td>
          <div class="service-row-title">${escHtml(svc.title)}</div>
          <div class="service-row-desc">${escHtml(svc.description || 'No description provided.')}</div>
        </td>
        <td>${escHtml(categoryMeta[svc.category]?.label || svc.category)}</td>
        <td>${price}</td>
        <td>
          <div class="service-actions">
            <button type="button" class="btn btn-outline" data-edit-service="${svc.id}">Edit</button>
            <button type="button" class="btn btn-danger-soft" data-delete-service="${svc.id}">Delete</button>
          </div>
        </td>
      `;

      servicesTableBody.appendChild(row);
    });
  }

  function showServicesState(state) {
    servicesLoading.classList.toggle('hidden', state !== 'loading');
    servicesEmpty.classList.toggle('hidden', state !== 'empty');
    servicesError.classList.toggle('hidden', state !== 'error');
    servicesTableWrap.classList.toggle('hidden', state !== 'table');
  }

  function populateFormForEdit(service) {
    currentMode = 'edit';
    clearFeedback();
    serviceIdInput.value = service.id;
    titleInput.value = service.title || '';
    descInput.value = service.description || '';
    priceInput.value = service.price ?? '';
    imageInput.value = service.image_url || '';
    cancelEditBtn?.classList.remove('hidden');
    submitBtn.querySelector('.btn-text').textContent = 'Save Changes';

    catBtns.forEach((btn) => {
      btn.classList.toggle('selected', btn.dataset.value === service.category);
    });
    categoryInput.value = service.category || '';

    const meta = categoryMeta[service.category] || { label: 'Category', icon: 'âœ¦', cls: '' };
    previewCat.textContent = meta.label;
    previewCat.className = `svc-card-category preview-cat ${meta.cls}`;
    previewTitle.textContent = service.title || 'Service title will appear here';
    previewDesc.textContent = service.description || 'Description preview will appear here as you typeâ€¦';

    const val = parseFloat(service.price);
    previewPrice.textContent = !isNaN(val)
      ? val.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
      : '$0.00';

    if (service.image_url) {
      imgPreview.src = service.image_url;
      imgPreviewWrap.classList.remove('hidden');
      previewImg.innerHTML = '';
      previewImg.style.backgroundImage = `url('${service.image_url}')`;
      previewImg.style.backgroundSize = 'cover';
      previewImg.style.backgroundPosition = 'center';
    } else {
      imgPreviewWrap.classList.add('hidden');
      setPreviewPlaceholder(meta.icon);
    }

    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function deleteService(serviceId) {
    const confirmed = window.confirm('Are you sure you want to delete this service?');
    if (!confirmed) return;

    try {
      const res = await fetch('backend/delete_service.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_id: serviceId }),
      });
      const data = await res.json();

      if (!res.ok || !data.success) {
        throw new Error(data.message || 'Failed to delete service.');
      }

      showFeedback(`Service #${serviceId} was deleted successfully.`, 'success');
      if (serviceIdInput.value && parseInt(serviceIdInput.value, 10) === serviceId) {
        resetForm();
      }
      await loadServicesList();
    } catch (err) {
      showFeedback(err.message || 'Failed to delete service.', 'error');
    }
  }

  function addToRecentList(svc, id) {
    recentWrap.classList.remove('hidden');
    const meta = categoryMeta[svc.category] || { icon: 'âœ¦' };
    const price = svc.price.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

    const li = document.createElement('li');
    li.className = 'recent-item';
    li.innerHTML = `
      <span class="recent-item-icon">${meta.icon}</span>
      <div class="recent-item-info">
        <div class="recent-item-title">${escHtml(svc.title)}</div>
        <div class="recent-item-price">${price} Â· ID #${id}</div>
      </div>
    `;
    recentList.prepend(li);

    while (recentList.children.length > 5) {
      recentList.removeChild(recentList.lastChild);
    }
  }

  function setPreviewPlaceholder(icon) {
    previewImg.style.backgroundImage = '';
    previewImg.style.backgroundSize = '';
    previewImg.style.backgroundPosition = '';
    previewImg.textContent = icon;
  }

  function setLoading(on) {
    const text = submitBtn.querySelector('.btn-text');
    const spinner = submitBtn.querySelector('.btn-spinner');
    submitBtn.disabled = on;
    text.hidden = on;
    spinner.hidden = !on;
  }

  function showFeedback(msg, type) {
    feedback.textContent = msg;
    feedback.className = `admin-feedback ${type}`;
    feedback.classList.remove('hidden');
    feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearFeedback() {
    feedback.textContent = '';
    feedback.className = 'admin-feedback hidden';
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }
})();
