'use strict';
document.querySelectorAll('[data-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.open)?.showModal()));
// Forms that ask before doing something that cannot be undone.
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.close)?.close()));
// Draw the submitted geometry; all labels are canvas text, never executable HTML.
function drawPlan(canvas){
  const ctx=canvas.getContext('2d');if(!ctx)return;
  let rooms;try{rooms=JSON.parse(canvas.dataset.rooms)}catch{return}
  const rect=canvas.getBoundingClientRect(),ratio=Math.min(window.devicePixelRatio||1,2);
  const w=Math.max(240,rect.width),h=Math.max(240,rect.height);canvas.width=w*ratio;canvas.height=h*ratio;ctx.scale(ratio,ratio);ctx.fillStyle='#f7f9fb';ctx.fillRect(0,0,w,h);
  ctx.strokeStyle='#dbe3ec';ctx.lineWidth=.5;for(let x=0;x<w;x+=20){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,h);ctx.stroke()}for(let y=0;y<h;y+=20){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(w,y);ctx.stroke()}
  if(!Array.isArray(rooms)||!rooms.length){ctx.fillStyle='#58697e';ctx.font='12px Inter, sans-serif';ctx.textAlign='center';ctx.fillText('No rooms in this submitted snapshot',w/2,h/2);return}
  // ARCHIspace room coordinates describe the room centre.
  const x0=Math.min(...rooms.map(r=>r.x-r.w/2)),x1=Math.max(...rooms.map(r=>r.x+r.w/2));
  const z0=Math.min(...rooms.map(r=>r.z-r.d/2)),z1=Math.max(...rooms.map(r=>r.z+r.d/2));
  const scale=Math.min((w-64)/Math.max(x1-x0,.1),(h-64)/Math.max(z1-z0,.1));
  const ox=(w-(x1-x0)*scale)/2,oy=(h-(z1-z0)*scale)/2;
  rooms.forEach((r,i)=>{const x=ox+(r.x-r.w/2-x0)*scale,y=oy+(r.z-r.d/2-z0)*scale,rw=r.w*scale,rh=r.d*scale;ctx.fillStyle=['#e1eef1','#f4eee1','#e3eaf2','#ecf1f5'][i%4];ctx.fillRect(x,y,rw,rh);ctx.strokeStyle='#435c72';ctx.lineWidth=2;ctx.strokeRect(x,y,rw,rh);ctx.save();ctx.beginPath();ctx.rect(x+3,y+3,Math.max(0,rw-6),Math.max(0,rh-6));ctx.clip();ctx.textAlign='center';ctx.fillStyle='#263f55';ctx.font='11px Inter, sans-serif';ctx.fillText(r.name,x+rw/2,y+rh/2-3);ctx.font='9px "IBM Plex Mono", monospace';ctx.fillText(`${r.w.toFixed(1)} × ${r.d.toFixed(1)} m`,x+rw/2,y+rh/2+12);ctx.restore()});
  ctx.textAlign='left';ctx.font='9px "IBM Plex Mono",monospace';ctx.fillStyle='#58697e';ctx.fillText('ROOM FOOTPRINTS / METRIC / CONCEPT ONLY',14,h-12);
}
document.querySelectorAll('.review-plan').forEach(canvas=>{drawPlan(canvas);if('ResizeObserver' in window)new ResizeObserver(()=>drawPlan(canvas)).observe(canvas)});
