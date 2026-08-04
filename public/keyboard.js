/* ALDAWAPHARMACY global keyboard navigation */
(function(){
 'use strict';
 const focusables='a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
 function all(){return [...document.querySelectorAll(focusables)].filter(x=>x.offsetParent!==null && !x.hasAttribute('aria-hidden'));}
 function move(dir){const a=all(),i=a.indexOf(document.activeElement);if(!a.length)return;const n=Math.max(0,Math.min(a.length-1,(i<0?(dir>0?0:a.length-1):i+dir)));a[n].focus();a[n].scrollIntoView({block:'nearest'});}
 document.addEventListener('keydown',function(e){
   const tag=(document.activeElement?.tagName||'').toLowerCase(), typing=['input','textarea','select'].includes(tag);
   if(e.key==='F1'){e.preventDefault();window.location.href='index.php?action=dashboard';return;}
   if(e.altKey&&e.key.toLowerCase()==='d'){e.preventDefault();window.location.href='index.php?action=dashboard';return;}
   if(e.altKey&&e.key.toLowerCase()==='p'){e.preventDefault();window.location.href='pos.php';return;}
   if(e.altKey&&e.key.toLowerCase()==='m'){e.preventDefault();window.location.href='manage.php';return;}
   if(e.altKey&&e.key.toLowerCase()==='l'){e.preventDefault();window.location.href='logout.php';return;}
   if(e.key==='Escape'){
     document.querySelectorAll('[open]').forEach(x=>x.removeAttribute('open'));
     const visible=document.querySelector('.modal:not([hidden]),.dialog:not([hidden]),.medicine-results');
     if(visible && visible.classList.contains('medicine-results')) visible.style.display='none';
     return;
   }
   if(!typing && e.key==='ArrowDown'){e.preventDefault();move(1);return;}
   if(!typing && e.key==='ArrowUp'){e.preventDefault();move(-1);return;}
   if(!typing && e.key==='Enter' && document.activeElement?.matches('a,button')){document.activeElement.click();return;}
 });
 document.addEventListener('DOMContentLoaded',function(){
   document.body.classList.add('keyboard-enabled');
   const first=document.querySelector('main '+focusables);
   if(first && !document.activeElement.matches('input,textarea,select,button,a')) first.setAttribute('data-keyboard-first','1');
 });
})();
