'use strict';

let logsData=[], allLogsData=[], allLogsCache=[], logsInterval=null, currentPage='logs', actOffset=0, actFilter='all';
const LOGS_PER_PAGE=50, MODAL_LOGS_PER_PAGE=20;
const PALETTE=['#3b82f6','#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#84cc16','#f97316','#14b8a6','#a855f7'];

function esc(t){if(!t)return '';return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function escRx(s){return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}
function parseMsg(text){
  if(!text)return '';
  text=esc(text);
  text=text.replace(/```(\w+)?\n?([\s\S]*?)```/g,(_,_l,code)=>`<pre class="codeblock">${code}</pre>`);
  text=text.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
  text=text.replace(/\\n/g,'\n');
  return text.replace(/\n/g,'<br>');
}
function fmtDate(ts){return new Date((ts||0)*1000).toLocaleString('en-GB')}

function initTheme(){if(localStorage.getItem('radeon_theme')==='light')document.documentElement.classList.add('light')}
function toggleTheme(){document.documentElement.classList.toggle('light');localStorage.setItem('radeon_theme',document.documentElement.classList.contains('light')?'light':'dark')}

let _verTimer=null,_verShown=false;
async function checkVersion(){
  if(_verShown)return;
  try{
    const b=document.body,css=b.dataset.cssVersion||'',js=b.dataset.jsVersion||'';
    if(!css||!js)return;
    const res=await fetch('version/check_version.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN||''},body:JSON.stringify({css_version:css,js_version:js})});
    if(!res.ok)return;
    const data=await res.json();
    if(data.update_available){document.getElementById('cfUpdateBar')?.classList.add('show');_verShown=true;clearInterval(_verTimer)}
  }catch(_){}
}
function startVersionCheck(){setTimeout(()=>{checkVersion();_verTimer=setInterval(checkVersion,8000)},2000)}

function setActiveNav(id){
  document.querySelectorAll('[data-nav]').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll(`[data-nav="${id}"]`).forEach(el=>el.classList.add('active'));
}
function setContent(html){const mc=document.getElementById('cfMain');if(mc)mc.innerHTML=html}

/* LOGS */
function openLogs(){
  currentPage='logs';setActiveNav('logs');
  clearInterval(logsInterval);logsInterval=null;
  setContent(`
    <div class="cf-content cf-page-enter">
      <div class="cf-page-header-row" style="margin-bottom:24px;">
        <div>
          <div class="cf-breadcrumb"><span>Admin</span><span class="cf-breadcrumb-sep">/</span><span>Logs</span></div>
          <h1 class="cf-page-title">Log Monitor</h1>
          <p class="cf-page-sub">Real-time categorized log viewer — auto-refresh every 15s</p>
        </div>
        <div class="cf-page-actions">
          <span id="cfLogCountBadge" class="cf-badge cf-badge-blue">Loading…</span>
        </div>
      </div>
      <div id="cfChart"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:10px;flex-wrap:wrap;">
        <p class="cf-section-label" style="margin:0;">Categories</p>
        <div class="cf-search-wrap" style="max-width:300px;flex:1;">
          <svg class="cf-search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input class="cf-input" id="cfSearch" placeholder="Filter categories or messages…">
        </div>
      </div>
      <div id="cfCategories" class="cf-cat-grid"></div>
    </div>`);
  document.getElementById('cfSearch')?.addEventListener('input',renderLogs);
  loadLogs();
  logsInterval=setInterval(loadLogs,15000);
}

async function loadLogs(){
  if(currentPage!=='logs')return;
  try{
    const res=await fetch('api/logs.php?t='+Date.now());
    const data=await res.json();
    if(!Array.isArray(data))return;
    logsData=data;allLogsCache=data;
    const badge=document.getElementById('cfLogCountBadge');
    if(badge)badge.textContent=data.length.toLocaleString()+' entries';
    const nb=document.getElementById('cfNavLogsBadge');
    if(nb)nb.textContent=data.length.toLocaleString();
    renderLogs();renderChart(data);
  }catch(e){console.error(e)}
}

function renderLogs(){
  const q=(document.getElementById('cfSearch')?.value||'').toLowerCase();
  const con=document.getElementById('cfCategories');
  if(!con)return;
  con.innerHTML='';
  const grouped={};
  logsData.forEach(log=>{
    if(q&&!log.message?.toLowerCase().includes(q)&&!log.category?.toLowerCase().includes(q))return;
    const cat=log.category||'Uncategorized';
    if(!grouped[cat])grouped[cat]=[];
    grouped[cat].push(log);
  });
  const cats=Object.keys(grouped);
  if(!cats.length){
    con.innerHTML=`<div class="cf-empty" style="grid-column:1/-1"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><p>No logs found</p></div>`;
    return;
  }
  const shown=cats.slice(0,LOGS_PER_PAGE);
  shown.forEach(cat=>con.appendChild(makeCatBox(cat,grouped[cat])));
  if(cats.length>LOGS_PER_PAGE){
    const btn=document.createElement('button');
    btn.className='cf-load-more';btn.style.gridColumn='1/-1';
    btn.textContent=`Show ${cats.length-LOGS_PER_PAGE} more categories`;
    btn.onclick=()=>{cats.slice(LOGS_PER_PAGE).forEach(cat=>con.insertBefore(makeCatBox(cat,grouped[cat]),btn));btn.remove()};
    con.appendChild(btn);
  }
}

function makeCatBox(cat,logs){
  const box=document.createElement('div');box.className='cf-cat-box';
  box.innerHTML=`<div class="cf-cat-header"><span class="cf-cat-name">${esc(cat)}</span><span class="cf-cat-count">${logs.length}</span></div>`;
  box.querySelector('.cf-cat-header').onclick=()=>openCatModal(cat,logs);
  return box;
}

function renderChart(data){
  const wrap=document.getElementById('cfChart');
  if(!wrap||!data.length)return;
  const counts={};
  data.forEach(l=>{const c=l.category||'Uncategorized';counts[c]=(counts[c]||0)+1});
  const sorted=Object.entries(counts).sort((a,b)=>b[1]-a[1]).slice(0,10);
  const max=sorted[0]?.[1]||1;
  const bars=sorted.map(([cat,cnt],i)=>{
    const pct=((cnt/max)*100).toFixed(1);const col=PALETTE[i%PALETTE.length];
    return `<div class="cf-bar-row"><div class="cf-bar-label" title="${esc(cat)}">${esc(cat)}</div><div class="cf-bar-track"><div class="cf-bar-fill" style="width:${pct}%;background:${col}"></div></div><div class="cf-bar-count">${cnt}</div></div>`;
  }).join('');
  wrap.innerHTML=`<div class="cf-chart-card"><div class="cf-chart-title"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Top ${sorted.length} Categories</div>${bars}</div>`;
}

/* MODAL */
function openCatModal(category,logs){
  const overlay=document.getElementById('cfModal'),body=document.getElementById('cfModalBody'),title=document.getElementById('cfModalTitle'),search=document.getElementById('cfModalSearch');
  if(!overlay||!body||!title)return;
  title.textContent=category;body.innerHTML='';if(search)search.value='';
  allLogsData=logs;body.dataset.page='0';
  renderModalPage(logs,0);overlay.classList.add('open');
  body.onscroll=()=>{
    if(body.scrollTop+body.clientHeight>=body.scrollHeight-80){
      const page=parseInt(body.dataset.page)+1;
      if(page*MODAL_LOGS_PER_PAGE<allLogsData.length){body.dataset.page=page;renderModalPage(allLogsData,page)}
    }
  };
}
function renderModalPage(logs,page){
  const body=document.getElementById('cfModalBody');if(!body)return;
  const start=page*MODAL_LOGS_PER_PAGE;
  logs.slice(start,start+MODAL_LOGS_PER_PAGE).forEach(log=>body.appendChild(makeLogEl(log)));
}
function makeLogEl(log,highlight){
  const div=document.createElement('div');div.className='cf-log-entry';
  let msg=parseMsg(log.message||'');
  if(highlight){const rx=new RegExp('('+escRx(highlight)+')','gi');msg=msg.replace(rx,'<span class="cf-highlight">$1</span>')}
  div.innerHTML=`<div class="cf-log-msg">${msg}</div><div class="cf-log-meta"><span class="cf-log-time">${fmtDate(log.time)}</span></div>`;
  return div;
}
function handleModalSearch(e){
  const q=e.target.value.toLowerCase().trim();const body=document.getElementById('cfModalBody');if(!body)return;
  if(!q){body.innerHTML='';body.dataset.page='0';renderModalPage(allLogsData,0);return}
  const filtered=allLogsData.filter(l=>(l.message||'').toLowerCase().includes(q)||fmtDate(l.time).toLowerCase().includes(q));
  if(!filtered.length){body.innerHTML=`<div class="cf-no-results">No results for "<strong>${esc(e.target.value)}</strong>"</div>`;return}
  body.innerHTML='';filtered.forEach(log=>body.appendChild(makeLogEl(log,e.target.value.trim())));
}
function closeModal(){document.getElementById('cfModal')?.classList.remove('open')}

/* GLOBAL SEARCH */
function openSearch(){
  currentPage='search';setActiveNav('search');clearInterval(logsInterval);logsInterval=null;
  setContent(`
    <div class="cf-content cf-page-enter">
      <div style="margin-bottom:24px;">
        <div class="cf-breadcrumb"><span>Admin</span><span class="cf-breadcrumb-sep">/</span><span>Search</span></div>
        <h1 class="cf-page-title">Global Search</h1>
        <p class="cf-page-sub">Search across all log messages and categories</p>
      </div>
      <div class="cf-gsearch-header">
        <div class="cf-search-wrap" style="flex:1;">
          <svg class="cf-search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input class="cf-input" id="cfGSearch" placeholder="Search across all logs…" autofocus>
        </div>
        <span class="cf-gsearch-count" id="cfGCount"></span>
      </div>
      <div id="cfGResults"><div class="cf-gsearch-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><p>Type at least 2 characters to search</p></div></div>
    </div>`);
  if(!allLogsCache.length){fetch('api/logs.php?t='+Date.now()).then(r=>r.json()).then(d=>{if(Array.isArray(d))allLogsCache=d}).catch(()=>{})}
  document.getElementById('cfGSearch')?.addEventListener('input',e=>runGSearch(e.target.value.trim().toLowerCase(),e.target.value.trim()));
}
function runGSearch(q,raw){
  const res=document.getElementById('cfGResults'),cnt=document.getElementById('cfGCount');if(!res)return;
  if(q.length<2){if(cnt)cnt.textContent='';res.innerHTML=`<div class="cf-gsearch-placeholder"><p>Type at least 2 characters</p></div>`;return}
  const matched=allLogsCache.filter(l=>(l.message||'').toLowerCase().includes(q)||(l.category||'').toLowerCase().includes(q));
  if(cnt)cnt.textContent=`${matched.length} result${matched.length!==1?'s':''}`;
  if(!matched.length){res.innerHTML=`<div class="cf-gsearch-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><p>No results for "<strong>${esc(raw)}</strong>"</p></div>`;return}
  const rx=new RegExp('('+escRx(raw)+')','gi');
  res.innerHTML=matched.slice(0,200).map(l=>`<div class="cf-gsearch-result"><div class="cf-gsearch-cat">${esc(l.category||'Uncategorized')}</div><div class="cf-gsearch-msg">${parseMsg(l.message||'').replace(rx,'<span class="cf-highlight">$1</span>')}</div><div class="cf-gsearch-time">${fmtDate(l.time)}</div></div>`).join('');
  if(matched.length>200)res.innerHTML+=`<p style="text-align:center;padding:14px;color:var(--cf-text-3);font-size:12px;">Showing first 200 of ${matched.length}</p>`;
}

/* ACTIVITY */
function openActivity(){
  if(!window.IS_ADMIN)return;
  currentPage='activity';setActiveNav('activity');clearInterval(logsInterval);logsInterval=null;actOffset=0;actFilter='all';
  setContent(`
    <div class="cf-content cf-page-enter">
      <div class="cf-page-header-row" style="margin-bottom:20px;">
        <div>
          <div class="cf-breadcrumb"><span>Admin</span><span class="cf-breadcrumb-sep">/</span><span>Activity</span></div>
          <h1 class="cf-page-title">Activity Log</h1>
          <p class="cf-page-sub">Full audit trail — logins, user management, permission changes</p>
        </div>
        <div class="cf-page-actions">
          <div class="cf-search-wrap" style="width:220px;">
            <svg class="cf-search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input class="cf-input" id="cfActSearch" placeholder="Search activity…">
          </div>
        </div>
      </div>
      <div class="cf-activity-filters" id="cfActFilters">
        <button class="cf-activity-filter-btn active" data-filter="all">All</button>
        <button class="cf-activity-filter-btn" data-filter="login"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--cf-green);margin-right:5px;vertical-align:middle;"></span>Login</button>
        <button class="cf-activity-filter-btn" data-filter="logout"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--cf-yellow);margin-right:5px;vertical-align:middle;"></span>Logout</button>
        <button class="cf-activity-filter-btn" data-filter="delete"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--cf-red);margin-right:5px;vertical-align:middle;"></span>Delete</button>
        <button class="cf-activity-filter-btn" data-filter="perm"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--cf-blue);margin-right:5px;vertical-align:middle;"></span>Permissions</button>
      </div>
      <div class="cf-activity-table" id="cfActList"><div style="text-align:center;padding:40px;color:var(--cf-text-3);font-size:13px;">Loading…</div></div>
    </div>`);
  document.getElementById('cfActFilters')?.addEventListener('click',e=>{
    const btn=e.target.closest('[data-filter]');if(!btn)return;
    document.querySelectorAll('.cf-activity-filter-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');actFilter=btn.dataset.filter;actOffset=0;fetchActivity(true);
  });
  let st;
  document.getElementById('cfActSearch')?.addEventListener('input',()=>{clearTimeout(st);st=setTimeout(()=>{actOffset=0;fetchActivity(true)},300)});
  fetchActivity(true);
}

async function fetchActivity(reset){
  const list=document.getElementById('cfActList');if(!list)return;
  if(reset)actOffset=0;
  const searchQ=document.getElementById('cfActSearch')?.value?.trim()||'';
  const params=new URLSearchParams({limit:50,offset:actOffset});
  if(actFilter!=='all')params.append('filter',actFilter);
  if(searchQ)params.append('search',searchQ);
  try{
    const res=await fetch(`api/activity.php?${params}`,{headers:{'X-CSRF-Token':window.CSRF_TOKEN||''}});
    const data=await res.json();
    if(data.error){if(reset)list.innerHTML=`<p style="padding:20px;color:var(--cf-red);font-size:13px;">${esc(data.error)}</p>`;return}
    if(reset)list.innerHTML='';
    document.getElementById('cfActLoadMore')?.remove();
    if(!data.logs?.length){
      if(reset)list.innerHTML=`<div class="cf-empty"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No activity records found</p></div>`;
      return;
    }
    data.logs.forEach(log=>list.appendChild(makeActRow(log)));
    actOffset+=data.logs.length;
    if(actOffset<data.total){
      const btn=document.createElement('button');btn.id='cfActLoadMore';btn.className='cf-load-more-activity';
      btn.textContent=`Load more — ${data.total-actOffset} remaining`;btn.onclick=()=>fetchActivity(false);
      list.appendChild(btn);
    }
  }catch(e){if(reset)list.innerHTML=`<p style="padding:20px;color:var(--cf-red);font-size:13px;">Error: ${esc(e.message)}</p>`}
}

function makeActRow(log){
  const action=log.action||'',details=log.details||'';
  let dot='default';
  if(/logged in/i.test(action))dot='login';
  else if(/logged out/i.test(action))dot='logout';
  else if(/delet/i.test(action))dot='delete';
  else if(/permission|perm/i.test(action))dot='perm';
  const date=new Date(log.created_at).toLocaleString('en-GB');
  const row=document.createElement('div');row.className='cf-activity-row';
  row.innerHTML=`
    <div class="cf-activity-dot ${dot}"></div>
    <div class="cf-activity-info">
      <div class="cf-activity-main">
        <span class="cf-activity-user">${esc(log.username)}</span>
        <span class="cf-activity-action">— ${esc(action)}</span>
      </div>
      ${details?`<div class="cf-activity-detail">${esc(details)}</div>`:''}
      <div class="cf-activity-meta">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="m8 21 4-4 4 4M12 17v4"/></svg>${esc(log.ip||'—')}</span>
        <span><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${date}</span>
      </div>
    </div>`;
  return row;
}

document.addEventListener('DOMContentLoaded',()=>{
  initTheme();
  document.getElementById('cfThemeBtn')?.addEventListener('click',toggleTheme);
  document.addEventListener('click',e=>{
    const nav=e.target.closest('[data-nav]');if(!nav)return;
    const t=nav.dataset.nav;
    if(t==='logs')openLogs();
    if(t==='search')openSearch();
    if(t==='activity')openActivity();
  });
  document.getElementById('cfModalClose')?.addEventListener('click',closeModal);
  document.getElementById('cfModal')?.addEventListener('click',e=>{if(e.target===document.getElementById('cfModal'))closeModal()});
  document.getElementById('cfModalSearch')?.addEventListener('input',handleModalSearch);
  document.getElementById('cfUpdateReload')?.addEventListener('click',()=>location.reload(true));
  document.getElementById('cfUpdateDismiss')?.addEventListener('click',()=>{document.getElementById('cfUpdateBar')?.classList.remove('show');_verShown=false;startVersionCheck()});
  startVersionCheck();
  openLogs();
});