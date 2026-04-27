/**
 * js/auth.js
 * Handles the Login / Register modal lifecycle and
 * communicates with backend/login.php & backend/register.php.
 * On successful login, redirects to dashboard.html.
 * Also manages navbar state (auth buttons vs user nav links) and admin link visibility.
 */

(() => {
  'use strict';

  // ─── DOM REFS ────────────────────────────────────────────────
  const overlay         = document.getElementById('modalOverlay');
  const modal           = document.getElementById('authModal');
  const closeBtn        = document.getElementById('modalCloseBtn');
  const tabLogin        = document.getElementById('tabLogin');
  const tabRegister     = document.getElementById('tabRegister');
  const loginForm       = document.getElementById('loginForm');
  const registerForm    = document.getElementById('registerForm');
  const feedback        = document.getElementById('modalFeedback');
  const switchToReg     = document.getElementById('switchToRegister');
  const switchToLog     = document.getElementById('switchToLogin');

  // Buttons that open the modal
  const heroGetStarted  = document.getElementById('heroGetStartedBtn');
  const openLoginBtn    = document.getElementById('openLoginBtn');
  const openRegisterBtn = document.getElementById('openRegisterBtn');
  
  // Index.html navbar refs
  const authButtons     = document.getElementById('authButtons');
  const userNavLinks    = document.getElementById('userNavLinks');
  const adminNavLinks   = document.getElementById('adminNavLinks');
  const logoutBtn       = document.getElementById('logoutBtn');
  
  // Check auth status on page load (for index.html navbar)
  if (authButtons && userNavLinks) {
    checkAuthStatus();
  }
  
  async function checkAuthStatus() {
    try {
      const res = await fetch('backend/check_auth.php', { credentials: 'same-origin' });
      const data = await res.json();
      
      if (data.logged_in) {
        // Show user nav, hide auth buttons
        authButtons.style.display = 'none';
        userNavLinks.style.display = 'flex';
        
        // Show/hide admin links based on is_admin
        if (adminNavLinks) {
          if (data.user?.is_admin) {
            adminNavLinks.style.display = '';
            localStorage.setItem('eventsphere_is_admin', 'true');
          } else {
            adminNavLinks.style.display = 'none';
            localStorage.removeItem('eventsphere_is_admin');
          }
        }
      }
    } catch { /* ignore errors */ }
  }

  // Navbar scroll effect
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 10);
  }, { passive: true });

  // ─── MODAL OPEN / CLOSE ──────────────────────────────────────
  function openModal(tab = 'login') {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    switchTab(tab);
    clearFeedback();
  }

  function closeModal() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    clearFeedback();
    loginForm.reset();
    registerForm.reset();
  }

  // Open triggers
  heroGetStarted?.addEventListener('click',  () => openModal('register'));
  openLoginBtn?.addEventListener('click',    () => openModal('login'));
  openRegisterBtn?.addEventListener('click', () => openModal('register'));

  // Close triggers
  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
  });

  // ─── TAB SWITCHING ───────────────────────────────────────────
  function switchTab(tab) {
    clearFeedback();
    if (tab === 'login') {
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
      loginForm.classList.remove('hidden');
      registerForm.classList.add('hidden');
    } else {
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
      registerForm.classList.remove('hidden');
      loginForm.classList.add('hidden');
    }
  }

  tabLogin.addEventListener('click',    () => switchTab('login'));
  tabRegister.addEventListener('click', () => switchTab('register'));
  switchToReg?.addEventListener('click', (e) => { e.preventDefault(); switchTab('register'); });
  switchToLog?.addEventListener('click', (e) => { e.preventDefault(); switchTab('login'); });

  // ─── FEEDBACK HELPERS ────────────────────────────────────────
  function showFeedback(message, type = 'error') {
    feedback.textContent = message;
    feedback.className = `modal-feedback ${type}`;
  }

  function clearFeedback() {
    feedback.textContent = '';
    feedback.className = 'modal-feedback';
  }
  
  // ─── LOGOUT HANDLER (for index.html) ─────────────────────────
  logoutBtn?.addEventListener('click', async () => {
    localStorage.removeItem('eventsphere_is_admin');
    try {
      await fetch('backend/logout.php', { credentials: 'same-origin' });
    } finally {
      window.location.reload();
    }
  });

  // ─── LOADING STATE ───────────────────────────────────────────
  function setLoading(btn, isLoading) {
    const text    = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.btn-spinner');
    btn.disabled  = isLoading;
    text.hidden   = isLoading;
    spinner.hidden = !isLoading;
  }

  // ─── FETCH HELPER ────────────────────────────────────────────
  async function postJSON(url, body) {
    const res  = await fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
    });
    const data = await res.json();
    return { ok: res.ok, status: res.status, data };
  }

  // ─── LOGIN HANDLER ───────────────────────────────────────────
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFeedback();

    const submitBtn = document.getElementById('loginSubmitBtn');
    const email     = document.getElementById('loginEmail').value.trim();
    const password  = document.getElementById('loginPassword').value;

    if (!email || !password) {
      showFeedback('Please enter your email and password.');
      return;
    }

    setLoading(submitBtn, true);

    try {
      const { ok, data } = await postJSON('backend/login.php', { email, password });

      if (ok && data.success) {
        // Store admin status in localStorage for nav rendering
        if (data.user?.is_admin) {
          localStorage.setItem('eventsphere_is_admin', 'true');
        } else {
          localStorage.removeItem('eventsphere_is_admin');
        }
        
        showFeedback('Login successful! Redirecting…', 'success');
        setTimeout(() => { window.location.href = 'dashboard.html'; }, 800);
      } else {
        showFeedback(data.message || 'Login failed. Please try again.');
        setLoading(submitBtn, false);
      }
    } catch (err) {
      showFeedback('Could not reach the server. Please check your connection.');
      setLoading(submitBtn, false);
    }
  });

  // ─── REGISTER HANDLER ────────────────────────────────────────
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFeedback();

    const submitBtn = document.getElementById('registerSubmitBtn');
    const name      = document.getElementById('registerName').value.trim();
    const email     = document.getElementById('registerEmail').value.trim();
    const password  = document.getElementById('registerPassword').value;

    if (!name || !email || !password) {
      showFeedback('All fields are required.');
      return;
    }
    if (password.length < 8) {
      showFeedback('Password must be at least 8 characters long.');
      return;
    }

    setLoading(submitBtn, true);

    try {
      const { ok, data } = await postJSON('backend/register.php', { name, email, password });

      if (ok && data.success) {
        showFeedback('Account created! You can now log in.', 'success');
        registerForm.reset();
        setTimeout(() => switchTab('login'), 1500);
      } else {
        showFeedback(data.message || 'Registration failed. Please try again.');
      }
    } catch (err) {
      showFeedback('Could not reach the server. Please check your connection.');
    } finally {
      setLoading(submitBtn, false);
    }
  });

})();
