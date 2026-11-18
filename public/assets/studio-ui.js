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

/* First-visit tour: highlights one part of the studio at a time with a short note. Opens by itself the first
   time a user opens the studio (remembered per account in this browser) and again from Quick guide. */
const TOUR=[
 {title:'Welcome to the studio',text:'This short tour shows where things are. It takes about a minute, and you can open it again any time from Quick guide.'},
 {sel:'.workflow',title:'Four planning steps',text:'Import a blueprint, design the site, simulate weather and floods, then review readiness. Each step opens the matching tools.'},
 {sel:'#roomPalette',tab:'build',title:'Add rooms',text:'Click a room to add it to the floor you are editing, then drag it into place. Furniture and fixtures are listed below.'},
 {sel:'.floor-actions',tab:'build',title:'Floors',text:'Add an empty floor or copy the current one. Use stairs to connect floors: the readiness check counts the ways down from every upper floor.'},
 {sel:'.toolbar .seg',title:'3D, 2D plan and Top',text:'Switch views here. Move and resize rooms in the 2D plan, and check the shape and roof in 3D.'},
 {sel:'#lotMap',tab:'site',title:'Place your lot',text:'In Site, click the map to put the lot pin where the lot is, then turn the lot until it lines up with the street.'},
 {sel:'#fetchHazard',tab:'site',title:'Check hazards',text:'Check location looks up Project NOAH flood, landslide and storm-surge levels at the pin and within 500 m.'},
 {sel:'#readinessBtn',tab:'env',title:'Readiness and simulation',text:'In Environment, run the readiness report and the simulation. Mapped hazards at the lot lower the score.'},
 {sel:'#saveBtn',title:'Save and open',text:'Save keeps the design in your account (Ctrl+S). Open lists your saved designs and their versions.'},
 {sel:'#exportMenu',title:'Export',text:'Download the project file, a picture of the current view, or a printable readiness report you can save as PDF.'},
 {sel:'#quickGuide',title:'You are ready',text:'Quick guide has the planning steps and can start this tour again. This is concept planning, not structural certification.'}
];
const tourKey=q('#quickGuide')?.dataset.tourKey||'archispace-tour-v1';
let tourAt=-1,tourEls=null,tourBack=null;
function tourDone(){try{localStorage.setItem(tourKey,'1')}catch(e){}}
function tourSeen(){try{return localStorage.getItem(tourKey)==='1'}catch(e){return true}}
function tourBuild(){
 const shade=document.createElement('div');shade.className='tour-shade';
 const ring=document.createElement('div');ring.className='tour-ring';ring.setAttribute('aria-hidden','true');
 const card=document.createElement('div');card.className='tour-card';card.setAttribute('role','dialog');card.setAttribute('aria-modal','true');card.setAttribute('aria-labelledby','tourTitle');card.setAttribute('aria-describedby','tourText');
 card.innerHTML='<p class="tour-count" id="tourCount"></p><h2 id="tourTitle"></h2><p id="tourText"></p><div class="tour-actions"><button type="button" class="ghost" id="tourSkip">Skip tour</button><span></span><button type="button" id="tourPrev">Back</button><button type="button" class="primary" id="tourNext">Next</button></div>';
 document.body.append(shade,ring,card);
 card.querySelector('#tourSkip').onclick=()=>tourEnd();card.querySelector('#tourPrev').onclick=()=>tourGo(tourAt-1);card.querySelector('#tourNext').onclick=()=>tourAt===TOUR.length-1?tourEnd():tourGo(tourAt+1);
 return{shade,ring,card}}
function tourTarget(){const s=TOUR[tourAt];const el=s?.sel&&q(s.sel);if(!el)return null;const r=el.getBoundingClientRect();return r.width&&r.height?el:null}
function tourPlace(){
 if(tourAt<0)return;const{ring,card}=tourEls,el=tourTarget(),W=innerWidth,H=innerHeight,cw=card.offsetWidth,ch=card.offsetHeight,gap=12;
 if(!el){ring.hidden=true;card.style.left=Math.max(8,(W-cw)/2)+'px';card.style.top=Math.max(8,(H-ch)/2)+'px';return}
 const r=el.getBoundingClientRect(),pad=6;ring.hidden=false;
 Object.assign(ring.style,{left:r.left-pad+'px',top:r.top-pad+'px',width:r.width+pad*2+'px',height:r.height+pad*2+'px'});
 // Below the target if it fits, then above, then to the right, then to the left; always kept on screen.
 let x=r.left,y=r.bottom+gap+pad;
 if(y+ch>H-8){y=r.top-ch-gap-pad;if(y<8){y=Math.min(Math.max(8,r.top),H-ch-8);x=r.right+gap+pad;if(x+cw>W-8)x=r.left-cw-gap-pad}}
 card.style.left=Math.min(Math.max(8,x),W-cw-8)+'px';card.style.top=Math.min(Math.max(8,y),H-ch-8)+'px'}
function tourGo(i){
 if(i<0||i>=TOUR.length)return;tourAt=i;const s=TOUR[i],{card}=tourEls;
 if(s.tab)showPanel(s.tab);else q('#left')?.classList.remove('open');// on small screens the tools drawer slides in or out
 const el=s.sel&&q(s.sel);if(el)el.scrollIntoView({block:'center',inline:'nearest'});
 card.querySelector('#tourCount').textContent=(i+1)+' of '+TOUR.length;card.querySelector('#tourTitle').textContent=s.title;card.querySelector('#tourText').textContent=s.text;
 card.querySelector('#tourPrev').hidden=i===0;card.querySelector('#tourNext').textContent=i===TOUR.length-1?'Finish':'Next';
 tourPlace();requestAnimationFrame(tourPlace);setTimeout(tourPlace,400);card.querySelector('#tourNext').focus()}
function tourStart(){
 if(tourAt>=0)return;q('#studioGuide')?.open&&q('#studioGuide').close();document.querySelectorAll('details.menu[open]').forEach(d=>d.open=false);
 tourEls=tourEls||tourBuild();tourBack=document.activeElement;for(const n of Object.values(tourEls))n.hidden=false;document.body.classList.add('touring');tourGo(0)}
function tourEnd(){
 if(tourAt<0)return;tourAt=-1;for(const n of Object.values(tourEls))n.hidden=true;document.body.classList.remove('touring');tourDone();
 showPanel('build');q('#left')?.classList.remove('open');(tourBack&&tourBack.isConnected?tourBack:q('#quickGuide'))?.focus()}
// While the tour is open, keys stay in it: Escape skips, arrows move between steps, Tab stays on the card.
document.addEventListener('keydown',e=>{if(tourAt<0)return;
 if(e.key==='Escape'){e.preventDefault();tourEnd()}
 else if(e.key==='ArrowRight'){e.preventDefault();tourAt===TOUR.length-1?tourEnd():tourGo(tourAt+1)}
 else if(e.key==='ArrowLeft'){e.preventDefault();tourGo(tourAt-1)}
 else if(e.key==='Tab'){const b=[...tourEls.card.querySelectorAll('button:not([hidden])')],k=b.indexOf(document.activeElement);e.preventDefault();b[(k+(e.shiftKey?-1:1)+b.length)%b.length].focus()}
 e.stopPropagation()},true);
addEventListener('resize',tourPlace);document.addEventListener('scroll',tourPlace,true);
q('#startTour')?.addEventListener('click',tourStart);
// First visit: wait for the studio to finish starting, and do not open over another window.
if(!tourSeen())setTimeout(()=>{if(tourSeen()||document.querySelector('dialog[open],.modal.open'))return;tourStart()},900);
