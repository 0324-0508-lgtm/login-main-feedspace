// auth.js - Complete authentication handler for FeedSpace login/register/OTP
// Integrates backend PHP APIs + base.js utilities
// Usage: Load base.js first, then this script

// Shared utilities
let isLoading = false;

// School ID formatting (XXXX-XXXX)
document.addEventListener('DOMContentLoaded', function() {
  const loginIdentifierInput = document.getElementById('loginIdentifier');
  if (loginIdentifierInput) {
    loginIdentifierInput.addEventListener('input', function(e) {
      const raw = e.target.value;
      // Only auto-format school IDs when the value contains only digits and optional dashes.
      if (/^[0-9-]*$/.test(raw)) {
        let value = raw.replace(/[^0-9]/g, '');
        value = value.slice(0, 8);
        if (value.length > 4) {
          value = value.slice(0, 4) + '-' + value.slice(4);
        }
        e.target.value = value;
      }
    });
  }
});

// Password visibility toggle


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
const API_BASE = "/login-main-feedspace/auth/";
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

  const loginIdentifier = document.getElementById('loginIdentifier')?.value.trim();
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const schoolIdPattern = /^\d{4}-?\d{4}$/;

  if (!loginIdentifier || !(emailPattern.test(loginIdentifier) || schoolIdPattern.test(loginIdentifier))) {
    showToast('Enter a valid email address or School ID (XXXX-XXXX or XXXXXXXX)');
    return;
  }


  const btn = event.target.querySelector('button[type="submit"]');
  btn.dataset.originalText = btn.innerHTML;
  setLoading(btn, true);
  isLoading = true;

  let normalizedIdentifier = loginIdentifier;
  if (schoolIdPattern.test(loginIdentifier) && !loginIdentifier.includes('-')) {
    normalizedIdentifier = loginIdentifier.slice(0, 4) + '-' + loginIdentifier.slice(4);
  }

  const result = await postAuth('login.php', { identifier: normalizedIdentifier });


  setLoading(btn, false);
  isLoading = false;

  const errorMessage = typeof result === 'string'
    ? result
    : result?.error || result?.message;

if (result && result.success) {
    showToast('OTP sent to your email! 📧');
    setTimeout(() => {
        window.location.href = `verify-account.html?user_id=${result.user_id}`;
    }, 1000);
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
  const student_id = document.getElementById('student_id')?.value.trim();
  const email = document.getElementById('email')?.value.trim();

  const role = document.getElementById('role')?.value;
  const college = document.getElementById('college')?.value;

  if (!first_name || !last_name || !student_id || !email || !role || !college) {
    showToast('Please fill in all required fields.');
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

  try {
    const res = await fetch(`${API_BASE}register.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: new URLSearchParams({
        first_name,
        last_name,
        student_id,
        email,
        password,
        role,
        college
      }),
      credentials: 'same-origin'
    });

    const result = await res.json();

    if (result.success) {
      showToast('Account created successfully! Please log in.');
      setTimeout(() => {
        window.location.href = '/login-main-feedspace/main/html/main-feed.html';
      }, 1000);
    } else {
      showToast(result.message || 'Registration failed');
    }

  } catch (err) {
    showToast('Server error. Please try again.');
    console.error(err);
  }

  setLoading(btn, false);
  isLoading = false;

window.location.href = `main/html/verify-account.html?user_id=${result.user_id}`;
}

// 3. SEND OTP

async function sendOTP(data) {
  const result = await postAuth('send-otp.php', data);
  return result;
}

// 4. VERIFY OTP
async function verifyOTP(data) {
  console.log('verifyOTP called with data:', data);
  const result = await postAuth('verify-otp.php', data);
  console.log('verifyOTP result:', result);
  console.log('SENT TO SERVER:', data);

  if (result && result.success) {
    const savedUserId = data.user_id || result.user_id;
    if (savedUserId) {
      window.currentUserId = savedUserId;
      localStorage.setItem('currentUserId', savedUserId);
    }

    showToast('Login successful!');
    setTimeout(() => {
      if (data.mode === 'forgot') {
        window.location.href = 'reset-password.html';
} else {
        window.location.href = '/login-main-feedspace/main/html/main-feed.html';
      }

    }, 1500);

  } else {
    const errorMessage = result?.error || (typeof result === 'string' ? result : 'OTP verification failed');
    showToast(errorMessage);
  }
  return result;
}

// 5. LOGOUT (for dashboard)
async function handleLogout() {
  localStorage.removeItem('currentUserId');
  const result = await postAuth('logout.php', {});
  showToast('Logged out successfully');
  window.location.href = 'index.html';
}

// Export/attach to window for onclick="handleLogin(event)"
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;

window.handleLogout = handleLogout;

window.sendOTP = sendOTP;
window.verifyOTP = verifyOTP;

console.log('FeedSpace Auth.js loaded - Login/Register/OTP/Logout ready!');

