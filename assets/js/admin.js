(function(){
  document.addEventListener('DOMContentLoaded', function(){
    // Sidebar active state
    var links = document.querySelectorAll('#pue-nav a');
    var sections = Array.prototype.slice.call(links || []).map(function(a){
      try{ return document.querySelector(a.getAttribute('href')); }catch(e){ return null; }
    }).filter(Boolean);
    function activate(id){
      (links || []).forEach(function(a){ a.classList.toggle('active', a.getAttribute('href')==='#'+id); });
    }
    if(sections.length){
      if('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(e){ if(e.isIntersecting){ activate(e.target.id); } });
        },{rootMargin:'-40% 0px -55% 0px', threshold:0.01});
        sections.forEach(function(s){ io.observe(s); });
      } else {
        window.addEventListener('scroll', function(){
          var current = sections[0];
          sections.forEach(function(sec){ if(sec.getBoundingClientRect().top < 120) current = sec; });
          if(current) activate(current.id);
        });
      }
    }

    // Preserve scroll position across form submissions (prevents jump to top)
    try{
      var theme = document.querySelector('.pue-theme');
      if(theme){
        theme.addEventListener('submit', function(){
          try{ sessionStorage.setItem('pue_scroll', String(window.scrollY || window.pageYOffset || 0)); }catch(e){}
        }, true);
      }
      var y = null;
      try{ y = sessionStorage.getItem('pue_scroll'); sessionStorage.removeItem('pue_scroll'); }catch(e){}
      if(y !== null){
        var top = parseInt(y, 10);
        if(!isNaN(top)){
          setTimeout(function(){ window.scrollTo(0, top); }, 0);
        }
      }
    }catch(e){}

    // Color swatch + optional WP color picker for header background
    try{
      var colorInput = document.querySelector('input[name="pue_header_bg_color"]');
      var swatch = document.getElementById('pue-swatch-header-bg');
      var updateSwatch = function(){ if(!swatch || !colorInput) return; var v = (colorInput.value || '').trim(); if(v){ swatch.style.background = v; } };
      if(colorInput){
        colorInput.addEventListener('input', updateSwatch);
        colorInput.addEventListener('change', updateSwatch);
        // Initialize WP color picker when available
        if(window.jQuery && jQuery.fn && jQuery.fn.wpColorPicker){
          jQuery(colorInput).wpColorPicker({
            change: function(event, ui){ if(swatch && ui && ui.color){ swatch.style.background = ui.color.toString(); } },
            clear: function(){ if(swatch){ swatch.style.background = ''; } }
          });
        }
        // Initial paint
        updateSwatch();
      }
    }catch(e){}

    // Show "Done!" only for the section submitted
    try{
      var markDone = function(formId){
        var f = document.getElementById(formId);
        if(!f) return;
        var btn = f.querySelector('input.button-primary, button.button-primary');
        if(!btn) return;
        // Avoid duplicates
        if(!btn.nextElementSibling || !btn.nextElementSibling.classList || !btn.nextElementSibling.classList.contains('pue-done')){
          var span = document.createElement('span');
          span.className = 'pue-done';
          span.setAttribute('role','status');
          span.setAttribute('aria-live','polite');
          span.textContent = 'Done!';
          btn.insertAdjacentElement('afterend', span);
        }
      };
      var formIds = ['pue-form-notifications','pue-form-branding','pue-form-identity','pue-form-clear-log','pue-form-reset-defaults','pue-form-export-csv'];
      formIds.forEach(function(id){
        var f = document.getElementById(id);
        if(!f) return;
        f.addEventListener('submit', function(ev){
          var ajaxMap = {
            'pue-form-notifications': { action: 'pue_save_notifications', nonceField: 'pue_save_notifications_nonce' },
            'pue-form-branding':      { action: 'pue_save_branding',      nonceField: 'pue_save_branding_nonce' },
            'pue-form-identity':      { action: 'pue_save_identity',      nonceField: 'pue_save_identity_nonce' }
          };
          var isAjax = Object.prototype.hasOwnProperty.call(ajaxMap, id);
          if(isAjax){ ev.preventDefault(); }
          // show inline spinner and disable primary button until navigation
          try{
            var btn = f.querySelector('input.button-primary,button.button-primary');
            if(btn){
              if(btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-done')){
                btn.nextElementSibling.remove();
              }
              var spin = document.createElement('span');
              spin.className = 'pue-spinner';
              btn.classList.add('is-busy');
              btn.setAttribute('aria-busy','true');
              btn.disabled = true;
              btn.insertAdjacentElement('afterend', spin);
            }
          }catch(e){}
          if(isAjax){
            try{
              // Ensure TinyMCE editors push their content back to the underlying textarea
              try{
                if (window.tinymce && typeof tinymce.triggerSave === 'function') {
                  tinymce.triggerSave();
                } else if (window.tinyMCE && tinyMCE.triggerSave) {
                  tinyMCE.triggerSave();
                }
              }catch(e){}
              var data = new FormData(f);
              var conf = ajaxMap[id];
              data.append('action', conf.action);
              var url = (window.PUE_Ajax && PUE_Ajax.ajax_url) ? PUE_Ajax.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
              fetch(url, { method:'POST', credentials:'same-origin', body:data })
                .then(function(r){ return r.json(); })
                .then(function(res){
                  if(btn){
                    // Clean any prior status next to button (not the spinner)
                    if(btn.nextElementSibling && btn.nextElementSibling.classList && (btn.nextElementSibling.classList.contains('pue-done') || btn.nextElementSibling.classList.contains('pue-error'))){
                      btn.nextElementSibling.remove();
                    }
                    var after = (btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-spinner')) ? btn.nextElementSibling : btn;
                    var span = document.createElement('span');
                    if(res && res.success){
                      span.className = 'pue-done';
                      span.setAttribute('role','status');
                      span.setAttribute('aria-live','polite');
                      span.textContent = 'Done!';
                    } else {
                      span.className = 'pue-error';
                      var msg = (res && res.data && (res.data.msg || res.data.error)) ? res.data.msg || res.data.error : 'Save failed';
                      if(msg === 'bad_nonce'){ msg = 'Session expired — please refresh.'; }
                      if(msg === 'forbidden'){ msg = 'Permission denied.'; }
                      span.textContent = msg;
                    }
                    after.insertAdjacentElement('afterend', span);
                  }
                })
                .catch(function(){ /* ignore */ })
                .finally(function(){
                  try{
                    if(btn){ btn.classList.remove('is-busy'); btn.removeAttribute('aria-busy'); btn.disabled = false; }
                    if(btn && btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-spinner')){
                      btn.nextElementSibling.remove();
                    }
                  }catch(e){}
                });
            }catch(e){}
            return; // handled via AJAX
          }
          try{ sessionStorage.setItem('pue_done_section', id); }catch(e){}
        });
      });
      var sec = null;
      try{ sec = sessionStorage.getItem('pue_done_section'); sessionStorage.removeItem('pue_done_section'); }catch(e){}
      if(sec){ markDone(sec); }
    }catch(e){}

    // AJAX: Test Email (no page reload)
    try{
      var tform = document.getElementById('pue-test-form');
      if(tform){
        tform.addEventListener('submit', function(ev){
          ev.preventDefault();
          var btn = tform.querySelector('input.button-primary,button.button-primary');
          var result = document.getElementById('pue-test-result');
          var nonceEl = tform.querySelector('input[name="pue_test_email_nonce"]');
          var nonce = nonceEl ? nonceEl.value : (window.PUE_Ajax ? PUE_Ajax.nonce : '');
          // Remove any existing Done! before new request
          if(btn && btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-done')){
            btn.nextElementSibling.remove();
          }
          // Show busy state + spinner
          var spinner = document.createElement('span');
          spinner.className = 'pue-spinner';
          if(btn){ btn.classList.add('is-busy'); btn.setAttribute('aria-busy','true'); btn.disabled = true; btn.insertAdjacentElement('afterend', spinner); }
          var data = new FormData();
          data.append('action', 'pue_send_test_email');
          data.append('nonce', nonce);
          var url = (window.PUE_Ajax && PUE_Ajax.ajax_url) ? PUE_Ajax.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
          fetch(url, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function(r){ return r.json(); })
            .then(function(res){
              if(result && res && res.data && typeof res.data.html === 'string'){
                result.innerHTML = res.data.html;
              }
              // Show Done! next to button
              if(btn){
                if(!btn.nextElementSibling || !btn.nextElementSibling.classList || !btn.nextElementSibling.classList.contains('pue-done')){
                  var span = document.createElement('span');
                  span.className = 'pue-done';
                  span.setAttribute('role','status');
                  span.setAttribute('aria-live','polite');
                  span.textContent = 'Done!';
                  // If spinner exists, insert after spinner; otherwise after button
                  if(btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-spinner')){
                    btn.nextElementSibling.insertAdjacentElement('afterend', span);
                  } else {
                    btn.insertAdjacentElement('afterend', span);
                  }
                }
              }
            })
            .catch(function(){ /* ignore */ })
            .finally(function(){
              if(spinner && spinner.parentNode){ spinner.parentNode.removeChild(spinner); }
              if(btn){ btn.classList.remove('is-busy'); btn.removeAttribute('aria-busy'); btn.disabled = false; }
            });
        });
      }
    }catch(e){}

    // Error modal (View error)
    try{
      var modal = document.getElementById('pue-modal');
      var openModal = function(data){
        if(!modal) return;
        modal.querySelector('#pue-error-code').textContent = data.code || '';
        modal.querySelector('#pue-error-time').textContent = data.time || '';
        modal.querySelector('#pue-error-mode').textContent = data.mode || '';
        modal.querySelector('#pue-error-full').textContent = data.full || '';
        modal.classList.add('is-open');
        modal.style.display = 'block';
      };
      var closeModal = function(){ if(!modal) return; modal.classList.remove('is-open'); modal.style.display = 'none'; };
      document.addEventListener('click', function(ev){
        var a = ev.target.closest('.pue-err-link');
        if(a){
          ev.preventDefault();
          openModal({
            code: a.getAttribute('data-error-code') || '',
            time: a.getAttribute('data-error-time') || '',
            mode: a.getAttribute('data-error-mode') || '',
            full: a.getAttribute('data-error-full') || ''
          });
          return;
        }
        if(ev.target && ev.target.getAttribute('data-modal-close') === '1'){
          ev.preventDefault();
          closeModal();
        }
      });
      document.addEventListener('keydown', function(ev){ if(ev.key === 'Escape'){ closeModal(); } });
    }catch(e){}

    // Bulk Test (AJAX)
    try{
      var bform = document.getElementById('pue-form-bulk-test');
      if(bform){
        bform.addEventListener('submit', function(ev){
          ev.preventDefault();
          var btn = bform.querySelector('input.button-primary,button.button-primary');
          var result = document.getElementById('pue-bulk-result');
          if(btn){
            if(btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-done')){
              btn.nextElementSibling.remove();
            }
            var spin = document.createElement('span');
            spin.className = 'pue-spinner';
            btn.classList.add('is-busy');
            btn.setAttribute('aria-busy','true');
            btn.disabled = true;
            btn.insertAdjacentElement('afterend', spin);
          }
          var data = new FormData(bform);
          data.append('action','pue_bulk_test_send');
          var url = (window.PUE_Ajax && PUE_Ajax.ajax_url) ? PUE_Ajax.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
          fetch(url, { method:'POST', credentials:'same-origin', body:data })
            .then(function(r){ return r.json(); })
            .then(function(res){
              if(result && res && res.data && typeof res.data.html === 'string'){
                result.innerHTML = res.data.html;
              }
              if(btn){
                var span = document.createElement('span');
                span.className = (res && res.success) ? 'pue-done' : 'pue-error';
                span.setAttribute('role','status');
                span.setAttribute('aria-live','polite');
                span.textContent = (res && res.success) ? 'Done!' : ((res && res.data && res.data.error) ? res.data.error : 'Failed');
                if(btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-spinner')){
                  btn.nextElementSibling.insertAdjacentElement('afterend', span);
                } else {
                  btn.insertAdjacentElement('afterend', span);
                }
              }
            })
            .catch(function(){ if(result){ result.innerHTML = '<div class="pue-notice pue-notice--error"><p>Request failed.</p></div>'; } })
            .finally(function(){
              if(btn){ btn.classList.remove('is-busy'); btn.removeAttribute('aria-busy'); btn.disabled = false; }
              if(btn && btn.nextElementSibling && btn.nextElementSibling.classList && btn.nextElementSibling.classList.contains('pue-spinner')){
                btn.nextElementSibling.remove();
              }
            });
        });
      }
    }catch(e){}
  });
})();

(function(){
  document.addEventListener('DOMContentLoaded', function(){
    try{
      var btn = document.getElementById('pue-check-updates-btn');
      if(!btn) return;
      btn.addEventListener('click', function(ev){
        if(ev && typeof ev.preventDefault === 'function'){ ev.preventDefault(); }
        var nonceEl = document.getElementById('pue-force-check-nonce');
        var nonce = nonceEl ? nonceEl.value : '';
        var latestSpan = document.querySelector('.pue-updates-latest');
        var fetchedSpan = document.querySelector('.pue-updates-fetched');
        var dl = document.getElementById('pue-download-latest');
        // Spinner state
        var spin = document.createElement('span');
        spin.className = 'pue-spinner';
        btn.classList.add('is-busy'); btn.setAttribute('aria-busy','true'); btn.disabled = true; btn.insertAdjacentElement('afterend', spin);
        var data = new FormData();
        data.append('action', 'pue_force_check_now');
        data.append('nonce', nonce);
        var url = (window.PUE_Ajax && PUE_Ajax.ajax_url) ? PUE_Ajax.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        fetch(url, { method:'POST', credentials:'same-origin', body:data })
          .then(function(r){ return r.json(); })
            .then(function(res){
              if(res && res.success && res.data){
                if(latestSpan){ latestSpan.textContent = res.data.latest_version || ''; }
                if(fetchedSpan){ fetchedSpan.textContent = res.data.last_checked || ''; }
              // No download button in UI anymore; keep compatibility if present
              if(dl){ dl.style.display = 'none'; }
              // No changelog rendering in Settings card
              }
            })
          .catch(function(){ /* ignore */ })
          .finally(function(){
            if(spin && spin.parentNode){ spin.parentNode.removeChild(spin); }
            btn.classList.remove('is-busy'); btn.removeAttribute('aria-busy'); btn.disabled = false;
          });
      });
    }catch(e){}
  });
})();
