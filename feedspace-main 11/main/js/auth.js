// auth.js - Complete authentication handler for FeedSpace login/register/OTP
// Integrates backend PHP APIs + base.js utilities
// Usage: Load base.js first, then this script

// Shared utilities
let isLoading = false;

// School ID formatting (XXXX-XXXX)
document.addEventListener('DOMContentLoaded', function() {
  const schoolIdInput = document.getElementById('schoolId');
  if (schoolIdInput) {
    schoolIdInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/[^0-9]/g, '');
      value = value.slice(0, 8);
      if (value.length >= 4) {
        value = value.slice(0, 4) + '-' + value.slice(4);
      }
      e.target.value = value;
    });
  }
});

// Password visibility toggle
function togglePassword(id) {
  const input = document.getElementById(id);
  const icon = input.nextElementSibling?.querySelector('i');
  if (input && icon) {
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

// Show loading on button
function setLoading(btn, show = true) {
  if (show) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
  } else {
    btn.disabled = false;
    btn.innerHTML = btn.dataset.originalText || 'Log In';
  }
}

// API base path
const API_BASE = './feedspace-integration/main/api/users/auth/';

// Generic POST helper
async function postAuth(endpoint, data) {
  const formData = new FormData();
  for (let key in data) {
    formData.append(key, data[key]);
  }

  try {
    const res = await fetch(`${API_BASE}${endpoint}`, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    const contentType = res.headers.get('content-type') || '';
    const rawBody = await res.text();

    if (contentType.includes('application/json')) {
      try {
        const json = JSON.parse(rawBody);
        if (!res.ok) {
          return { error: json.error || json.message || `Request failed ${res.status}` };
        }
        return json;
      } catch (parseError) {
        return rawBody.trim();
      }
    }

    return rawBody.trim();
  } catch (err) {
    return { error: err.message };
  }
}

// 1. LOGIN
async function handleLogin(event) {
  event.preventDefault();
  if (isLoading) return;

  const schoolId = document.getElementById('schoolId')?.value.trim();
  const password = document.getElementById('password')?.value;

  if (!/^\d{4}-\d{4}$/.test(schoolId)) {
    showToast('Enter valid School ID (XXXX-XXXX)');
    return;
  }
  if (password.length < 6) {
    showToast('Password must be 6+ characters');
    return;
  }

  const btn = event.target.querySelector('button[type="submit"]');
  btn.dataset.originalText = btn.innerHTML;
  setLoading(btn, true);
  isLoading = true;

  const result = await postAuth('login.php', { user_id: schoolId, password });

  setLoading(btn, false);
  isLoading = false;

  const errorMessage = typeof result === 'string'
    ? result
    : result?.error;

  if (result === 'Login Successful!' || (result && result.success)) {
    showToast('Welcome to FeedSpace! 🚀');
    setTimeout(() => {
      window.location.href = 'feedspace-integration/main/html/main-feed.html';
    }, 1500);
    return;
  }

  showToast(errorMessage || 'Login failed');
}

// 2. REGISTER (for signup.html)
async function handleRegister(event) {
  event.preventDefault();
  if (isLoading) return;

  const first_name = document.getElementById('fname')?.value.trim();
  const last_name = document.getElementById('lname')?.value.trim();
  const email = document.getElementById('email')?.value.trim();
  const password = document.getElementById('password')?.value;
  const confirm = document.getElementById('confirm')?.value;
  const bio = document.getElementById('bio')?.value.trim() || '';

  if (!first_name || !last_name || !email || !password || !confirm) {
    showToast('Please fill in all required fields.');
    return;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showToast('Enter a valid email address');
    return;
  }
  if (password.length < 8) {
    showToast('Password must be at least 8 characters');
    return;
  }
  if (password !== confirm) {
    showToast('Passwords do not match');
    return;
  }

  const btn = event.target.querySelector('button[type="submit"]');
  btn.dataset.originalText = btn.innerHTML;
  setLoading(btn, true);
  isLoading = true;

  const result = await postAuth('register.php', {
    first_name,
    last_name,
    email,
    password,
    bio
  });

  setLoading(btn, false);
  isLoading = false;

  if (result && result.success) {
    showToast('Registered successfully! Verify your account.');
    setTimeout(() => {
      window.location.href = 'feedspace-integration/main/html/verify-account.html';
    }, 1200);
    return;
  }

  const errorMessage = result?.error || (typeof result === 'string' ? result : 'Registration failed');
  showToast(errorMessage);
}

// 3. FORGOT PASSWORD
async function handleForgotPassword(event) {
  event.preventDefault();
  if (isLoading) return;

  const email = document.getElementById('email')?.value.trim();
  if (!email) {
    showToast('Enter your email address');
    return;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showToast('Enter a valid email address');
    return;
  }

  const btn = event.target.querySelector('button[type="submit"]');
  btn.dataset.originalText = btn.innerHTML;
  setLoading(btn, true);
  isLoading = true;

  const result = await postAuth('forgot-password.php', { email });

  setLoading(btn, false);
  isLoading = false;

  if (result && result.success) {
    showToast(result.message || 'OTP sent to your email');
    setTimeout(() => {
      window.location.href = 'feedspace-integration/main/html/verify-account.html?mode=forgot';
    }, 1200);
    return;
  }

  const errorMessage = result?.error || (typeof result === 'string' ? result : 'Unable to send OTP');
  showToast(errorMessage);
}

// 4. SEND OTP
async function sendOTP(data) {
  const result = await postAuth('send-otp.php', data);
  showToast(result);
  return result;
}

// 4. VERIFY OTP
async function verifyOTP(data) {
  const result = await postAuth('verify-otp.php', data);
  if (result === 'OTP Verified!') {
    window.location.href = 'feedspace-integration/main/html/main-feed.html';
  } else {
    showToast(result);
  }
}

// 5. LOGOUT (for dashboard)
async function handleLogout() {
  const result = await postAuth('logout.php', {});
  showToast('Logged out successfully');
  window.location.href = 'index.html';
}

// Export/attach to window for onclick="handleLogin(event)"
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleForgotPassword = handleForgotPassword;
window.handleLogout = handleLogout;
window.togglePassword = togglePassword;
window.sendOTP = sendOTP;
window.verifyOTP = verifyOTP;

console.log('FeedSpace Auth.js loaded - Login/Register/OTP/Logout ready!');

