// debug-click.js: add temporary listeners to determine why dropdown is not clickable
(function(){
  function log(...args){ try{ console.log('[debug]', ...args);}catch(e){} }

  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.options-btn');
    if(btn){
      log('options-btn clicked', { targetTag: e.target.tagName, targetClass: e.target.className, postCard: !!btn.closest('.post-card') });
    }

    const menu = e.target.closest && e.target.closest('.post-options-menu');
    if(menu){
      log('menu clicked', { target: e.target.tagName, class: e.target.className });
    }
  }, true);

  document.addEventListener('mousedown', function(e){
    const btn = e.target.closest && e.target.closest('.options-btn');
    if(btn){
      log('options-btn mousedown', { x: e.clientX, y: e.clientY });
    }
  }, true);

})();

