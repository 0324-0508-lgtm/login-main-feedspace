// ============================================
//  communities.js — Logic for communities.html
// ============================================

// ---- Join / Leave Community ----

function toggleJoin(btn) {
  const isJoined = btn.classList.contains('joined');

  if (isJoined) {
    btn.classList.remove('joined');
    btn.textContent = 'Join Community';
    showToast('Left community.');
  } else {
    btn.classList.add('joined');
    btn.textContent = '✓ Joined';
    showToast('Joined community! 🎉');
  }
}
