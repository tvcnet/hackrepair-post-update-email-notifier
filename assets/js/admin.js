(function(){
  var cfg = window.PUE_Ajax || {};
  var t = cfg.i18n || {};
  function str(key, fallback){ return t[key] || fallback; }
  function ajaxUrl(){ return cfg.ajax_url || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : ''); }
  function post(data){
    return fetch(ajaxUrl(), { method:'POST', credentials:'same-origin', body:data }).then(function(r){ return r.json(); });
  }

  function clearFeedback(btn){
    if(!btn) return;
    var node = btn.nextElementSibling;
    while(node && node.classList){
      var next = node.nextElementSibling;
      if(node.classList.contains('pue-done') || node.classList.contains('pue-error')){ node.remove(); }
      else if(!node.classList.contains('pue-spinner')){ break; }
      node = next;
    }
  }
  function showFeedback(btn, success, message){
    if(!btn) return;
    clearFeedback(btn);
    var span = document.createElement('span');
    span.className = success ? 'pue-done' : 'pue-error';
    span.setAttribute('role','status');
    span.setAttribute('aria-live','polite');
    span.textContent = message || (success ? str('done','Done!') : str('failed','Request failed. Please try again.'));
    var after = btn.nextElementSibling && btn.nextElementSibling.classList.contains('pue-spinner') ? btn.nextElementSibling : btn;
    after.insertAdjacentElement('afterend', span);
  }
  function busy(btn, on){
    if(!btn) return;
    if(on){
      clearFeedback(btn);
      var spin = document.createElement('span');
      spin.className = 'pue-spinner';
      btn.classList.add('is-busy'); btn.setAttribute('aria-busy','true'); btn.disabled = true;
      btn.insertAdjacentElement('afterend', spin);
      return;
    }
    btn.classList.remove('is-busy'); btn.removeAttribute('aria-busy'); btn.disabled = false;
    var s = btn.nextElementSibling;
    if(s && s.classList && s.classList.contains('pue-spinner')){ s.remove(); }
  }
  function errorText(res, fallback){
    var msg = res && res.data && (res.data.msg || res.data.error);
    if(msg === 'bad_nonce') return str('expired','Session expired — please refresh.');
    if(msg === 'forbidden') return str('forbidden','Permission denied.');
    return msg || fallback;
  }
  function syncEditors(){
    try{ if(window.tinymce && typeof window.tinymce.triggerSave === 'function'){ window.tinymce.triggerSave(); } }catch(e){}
  }

  // Accessible modal: focus moves in, Tab stays inside, Escape closes, focus returns.
  var openModalEl = null, returnFocus = null;
  function focusables(el){
    return Array.prototype.filter.call(el.querySelectorAll('a[href],button,iframe,input,select,textarea,[tabindex]:not([tabindex="-1"])'), function(n){ return !n.disabled && n.offsetParent !== null; });
  }
  function openModal(el, trigger){
    if(!el) return;
    returnFocus = trigger || document.activeElement;
    el.hidden = false; el.style.display = 'block'; el.classList.add('is-open'); el.setAttribute('aria-hidden','false');
    openModalEl = el;
    var close = el.querySelector('.pue-modal__close');
    (close || el.querySelector('.pue-modal__dialog')).focus();
  }
  function closeModal(){
    if(!openModalEl) return;
    var el = openModalEl;
    el.classList.remove('is-open'); el.style.display = 'none'; el.hidden = true; el.setAttribute('aria-hidden','true');
    openModalEl = null;
    if(returnFocus && typeof returnFocus.focus === 'function'){ returnFocus.focus(); }
  }
  document.addEventListener('keydown', function(ev){
    if(!openModalEl) return;
    if(ev.key === 'Escape'){ ev.preventDefault(); closeModal(); return; }
    if(ev.key === 'Tab'){
      var list = focusables(openModalEl);
      if(!list.length){ ev.preventDefault(); return; }
      var first = list[0], last = list[list.length - 1];
      if(ev.shiftKey && document.activeElement === first){ ev.preventDefault(); last.focus(); }
      else if(!ev.shiftKey && document.activeElement === last){ ev.preventDefault(); first.focus(); }
    }
  });

  document.addEventListener('DOMContentLoaded', function(){
    // Sidebar active state
    var links = Array.prototype.slice.call(document.querySelectorAll('#pue-nav a'));
    var sections = links.map(function(a){ try{ return document.querySelector(a.getAttribute('href')); }catch(e){ return null; } }).filter(Boolean);
    function activate(id){ links.forEach(function(a){ a.classList.toggle('active', a.getAttribute('href') === '#' + id); }); }
    if(sections.length && 'IntersectionObserver' in window){
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if(e.isIntersecting){ activate(e.target.id); } });
      }, { rootMargin:'-40% 0px -55% 0px', threshold:0.01 });
      sections.forEach(function(s){ io.observe(s); });
    }

    // Confirmation prompts declared in markup (data-confirm) instead of inline handlers.
    document.addEventListener('click', function(ev){
      var el = ev.target.closest('[data-confirm]');
      if(el && !window.confirm(el.getAttribute('data-confirm'))){ ev.preventDefault(); ev.stopImmediatePropagation(); }
    }, true);

    // Color swatch + WP color picker
    try{
      var colorInput = document.querySelector('input[name="pue_header_bg_color"]');
      var swatch = document.getElementById('pue-swatch-header-bg');
      var paint = function(v){ if(swatch && v){ swatch.style.background = v; } };
      if(colorInput){
        colorInput.addEventListener('input', function(){ paint(colorInput.value.trim()); });
        if(window.jQuery && jQuery.fn && jQuery.fn.wpColorPicker){
          jQuery(colorInput).wpColorPicker({
            change: function(event, ui){ if(ui && ui.color){ paint(ui.color.toString()); } },
            clear: function(){ if(swatch){ swatch.style.background = ''; } }
          });
        }
      }
    }catch(e){}

    // Section saves over AJAX; the same forms post to admin-post.php without JavaScript.
    ['pue-form-notifications','pue-form-branding','pue-form-identity'].forEach(function(id){
      var f = document.getElementById(id);
      if(!f) return;
      f.addEventListener('submit', function(ev){
        ev.preventDefault();
        var btn = f.querySelector('input.button-primary,button.button-primary');
        syncEditors();
        busy(btn, true);
        post(new FormData(f))
          .then(function(res){ showFeedback(btn, !!(res && res.success), res && res.success ? str('done','Done!') : errorText(res, str('saveFailed','Save failed. Please try again.'))); })
          .catch(function(){ showFeedback(btn, false); })
          .finally(function(){ busy(btn, false); var x = btn && btn.nextElementSibling; if(x && x.classList.contains('pue-spinner')){ x.remove(); } });
      });
    });

    // Test email
    var tform = document.getElementById('pue-test-form');
    if(tform){
      tform.addEventListener('submit', function(ev){
        ev.preventDefault();
        var btn = tform.querySelector('input.button-primary,button.button-primary');
        var result = document.getElementById('pue-test-result');
        if(result){ result.textContent = ''; }
        busy(btn, true);
        post(new FormData(tform))
          .then(function(res){
            if(result && res && res.data && typeof res.data.html === 'string'){ result.innerHTML = res.data.html; }
            showFeedback(btn, !!(res && res.success), res && res.success ? str('done','Done!') : errorText(res, str('notAccepted','Email was not accepted for sending. Review the message and try again.')));
          })
          .catch(function(){ showFeedback(btn, false); })
          .finally(function(){ busy(btn, false); });
      });
    }

    // Bulk test
    var bform = document.getElementById('pue-form-bulk-test');
    if(bform){
      bform.addEventListener('submit', function(ev){
        ev.preventDefault();
        var count = parseInt((bform.querySelector('[name="pue_bulk_count"]') || {}).value, 10) || 0;
        var email = (bform.querySelector('[name="pue_bulk_email"]') || {}).value || '';
        if(count > 25 && !window.confirm(str('bulkConfirm','This sends %1$d test emails to %2$s. Continue?').replace('%1$d', count).replace('%2$s', email))){ return; }
        var btn = bform.querySelector('input.button-primary,button.button-primary');
        var result = document.getElementById('pue-bulk-result');
        if(result){ result.textContent = ''; }
        busy(btn, true);
        post(new FormData(bform))
          .then(function(res){
            if(result && res && res.data && typeof res.data.html === 'string'){ result.innerHTML = res.data.html; }
            showFeedback(btn, !!(res && res.success), res && res.success ? str('done','Done!') : errorText(res, str('failed','Request failed. Please try again.')));
          })
          .catch(function(){ showFeedback(btn, false); })
          .finally(function(){ busy(btn, false); });
      });
    }

    // Clear Log / Reset submit normally and return with a notice; show a spinner meanwhile.
    ['pue-form-clear-log','pue-form-reset-defaults'].forEach(function(id){
      var f = document.getElementById(id);
      if(f){ f.addEventListener('submit', function(){ busy(f.querySelector('.button-primary'), true); }); }
    });

    // Live recipient count
    var roles = document.getElementById('pue-roles');
    var countEl = document.getElementById('pue-recipient-count');
    var readNonce = document.getElementById('pue-read-nonce');
    var exclude = document.getElementById('pue_exclude_updater');
    var countTimer = null;
    function refreshCount(){
      if(!roles || !countEl || !readNonce) return;
      clearTimeout(countTimer);
      countTimer = setTimeout(function(){
        var data = new FormData();
        data.append('action','pue_count_recipients');
        data.append('pue_read_nonce', readNonce.value);
        roles.querySelectorAll('input[type="checkbox"]:checked').forEach(function(c){ data.append('pue_roles[]', c.value); });
        if(exclude && exclude.checked){ data.append('pue_exclude_updater','1'); }
        post(data).then(function(res){ if(res && res.success && res.data){ countEl.textContent = res.data.text; } }).catch(function(){});
      }, 250);
    }
    if(roles){ roles.addEventListener('change', refreshCount); }
    if(exclude){ exclude.addEventListener('change', refreshCount); }

    // Preview without sending (uses unsaved form values)
    var previewBtn = document.getElementById('pue-preview-btn');
    var previewModal = document.getElementById('pue-preview-modal');
    if(previewBtn && previewModal && readNonce){
      previewBtn.addEventListener('click', function(){
        syncEditors();
        var data = new FormData();
        data.append('action','pue_preview_email');
        data.append('pue_read_nonce', readNonce.value);
        ['pue_subject','pue_message','pue_header_text','pue_header_bg_color','pue_footer_text'].forEach(function(name){
          var field = document.querySelector('[name="' + name + '"]');
          if(field){ data.append(name, field.value); }
        });
        busy(previewBtn, true);
        post(data)
          .then(function(res){
            if(!res || !res.success || !res.data){ showFeedback(previewBtn, false, str('previewFailed','The preview could not be created. Please try again.')); return; }
            document.getElementById('pue-preview-subject').textContent = res.data.subject || '';
            document.getElementById('pue-preview-frame').srcdoc = res.data.html || '';
            openModal(previewModal, previewBtn);
          })
          .catch(function(){ showFeedback(previewBtn, false, str('previewFailed','The preview could not be created. Please try again.')); })
          .finally(function(){ busy(previewBtn, false); });
      });
    }

    // Error modal (View error) and generic close controls
    var errModal = document.getElementById('pue-modal');
    document.addEventListener('click', function(ev){
      var a = ev.target.closest('.pue-err-link');
      if(a && errModal){
        ev.preventDefault();
        errModal.querySelector('#pue-error-code').textContent = a.getAttribute('data-error-code') || '';
        errModal.querySelector('#pue-error-time').textContent = a.getAttribute('data-error-time') || '';
        errModal.querySelector('#pue-error-mode').textContent = a.getAttribute('data-error-mode') || '';
        errModal.querySelector('#pue-error-full').textContent = a.getAttribute('data-error-full') || '';
        openModal(errModal, a);
        return;
      }
      if(ev.target && ev.target.getAttribute && ev.target.getAttribute('data-modal-close') === '1'){ ev.preventDefault(); closeModal(); }
    });

    // Update checks always report failures and clear stale feedback on retry.
    var updateBtn = document.getElementById('pue-check-updates-btn');
    if(updateBtn){
      updateBtn.addEventListener('click', function(ev){
        ev.preventDefault();
        var nonceEl = document.getElementById('pue-force-check-nonce');
        var data = new FormData();
        data.append('action','pue_force_check_now');
        data.append('nonce', nonceEl ? nonceEl.value : '');
        busy(updateBtn, true);
        post(data)
          .then(function(res){
            if(res && res.success && res.data){
              var latest = document.querySelector('.pue-updates-latest');
              var fetched = document.querySelector('.pue-updates-fetched');
              if(latest){ latest.textContent = res.data.latest_version || ''; }
              if(fetched){ fetched.textContent = res.data.last_checked || ''; }
              showFeedback(updateBtn, true, str('updateDone','Update check complete.'));
            } else {
              showFeedback(updateBtn, false, errorText(res, str('updateFailed','Update information is temporarily unavailable. Plugin features continue to work.')));
            }
          })
          .catch(function(){ showFeedback(updateBtn, false); })
          .finally(function(){ busy(updateBtn, false); });
      });
    }
  });
})();
