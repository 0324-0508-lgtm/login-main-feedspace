// ============================================
//  liked.js — Logic for liked.html
// ============================================

// ---- Unlike a Page ----

function unlikePage(btn, pageName) {
  const card = btn.closest('.liked-card');

  if (confirm('Unlike "' + pageName + '"?')) {
    card.style.transition = 'opacity 0.28s, transform 0.28s';
    card.style.opacity = '0';
    card.style.transform = 'scale(0.95)';
    setTimeout(function() { card.remove(); }, 290);
    showToast('Unliked ' + pageName + '.');
  }
}
