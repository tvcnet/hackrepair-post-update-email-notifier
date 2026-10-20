/* Native help disclosures: mouse, keyboard and touch; no settings or network requests. */
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var helps = Array.prototype.slice.call(document.querySelectorAll('.pue-help'));
    function close(help){ help.open = false; help.dataset.pinned = ''; }
    function position(help){
      if(!help.open) return;
      var trigger = help.querySelector('summary').getBoundingClientRect();
      var content = help.querySelector('.pue-help-content');
      var box = content.getBoundingClientRect();
      var left = Math.max(12, Math.min(trigger.left, window.innerWidth - box.width - 12));
      var top = trigger.bottom + 8;
      if(top + box.height > window.innerHeight - 12){ top = Math.max(12, trigger.top - box.height - 8); }
      content.style.left = left + 'px'; content.style.top = top + 'px';
    }
    function open(help){
      helps.forEach(function(other){ if(other !== help) close(other); });
      help.open = true; position(help);
    }
    helps.forEach(function(help){
      help.classList.add('pue-help-ready');
      var summary = help.querySelector('summary');
      help.addEventListener('pointerenter', function(ev){ if(ev.pointerType !== 'touch') open(help); });
      help.addEventListener('pointerleave', function(){ if(!help.dataset.pinned && !help.contains(document.activeElement)) close(help); });
      help.addEventListener('focusin', function(){ open(help); });
      help.addEventListener('focusout', function(ev){ if(!help.contains(ev.relatedTarget)) close(help); });
      summary.addEventListener('click', function(ev){
        ev.preventDefault();
        if(help.dataset.pinned){ close(help); }
        else { open(help); help.dataset.pinned = 'yes'; }
      });
      help.addEventListener('toggle', function(){ position(help); });
    });
    document.addEventListener('keydown', function(ev){ if(ev.key === 'Escape') helps.forEach(close); });
    document.addEventListener('pointerdown', function(ev){ helps.forEach(function(help){ if(!help.contains(ev.target)) close(help); }); });
    window.addEventListener('resize', function(){ helps.forEach(position); });
    window.addEventListener('scroll', function(){ helps.forEach(position); }, true);
  });
})();
