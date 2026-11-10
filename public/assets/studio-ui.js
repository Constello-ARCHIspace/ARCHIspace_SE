const q = s => document.querySelector(s);
function showPanel(tab, selector) {
  q(`[data-tab="${tab}"]`)?.click();
  q('#left')?.classList.add('open');
  if (selector) q(selector)?.closest('.sect')?.scrollIntoView({block:'start',behavior:'smooth'});
}
document.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-step]').forEach(b => {b.classList.toggle('active',b===button); if(b===button)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});
  switch (button.dataset.step) {
    case 'blueprint': showPanel('site','#bpDropzone'); q('#bpFileInput')?.click(); break;
    case 'design': showPanel('site','[data-s="site.hills"]'); break;
    case 'simulate': showPanel('env','[data-s="env.hour"]'); break;
    case 'readiness': showPanel('env','#readinessBtn');q('#readinessBtn')?.click();break;
  }
}));
document.querySelectorAll('[data-focus]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-focus]').forEach(b => b.classList.toggle('selected', b===button));
  const destinations={terrain:['site','[data-s="site.hills"]'],river:['site','#riverBtn'],landscape:['build','#objPalette'],furniture:['build','#objPalette']};
  showPanel(...destinations[button.dataset.focus]);
}));
q('#quickGuide')?.addEventListener('click',()=>q('#studioGuide').showModal());
q('#closeStudioGuide')?.addEventListener('click',()=>q('#studioGuide').close());
function positionPanels(){const top=q('.app')?.getBoundingClientRect().top;if(top!==undefined)document.documentElement.style.setProperty('--studio-top',`${top}px`);}
window.addEventListener('resize',positionPanels);positionPanels();
