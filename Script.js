// Client-side logic: add, list, drag-drop, auto-refresh, search, favorites, total
const addBtn = document.getElementById('addBtn');
const tvLinkInput = document.getElementById('tvLink');
const coinListEl = document.getElementById('coinList');
const coinTpl = document.getElementById('coinTpl');
const searchInput = document.getElementById('search');
const totalValueEl = document.getElementById('totalValue');
const autorefresh = document.getElementById('autorefresh');

let coins = []; // local list
let dragSrc = null;
let refreshInterval = null;

async function api(action, body = {}) {
  const url = `api.php?action=${action}`;
  const resp = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  return resp.json();
}

function render() {
  const q = searchInput.value.trim().toLowerCase();
  coinListEl.innerHTML = '';
  let total = 0;
  coins.forEach(c => {
    if (q && !(c.name.toLowerCase().includes(q) || c.symbol.toLowerCase().includes(q))) return;
    const node = coinTpl.content.cloneNode(true);
    const li = node.querySelector('li');
    li.dataset.id = c.cg_id;
    li.setAttribute('draggable','true');
    node.querySelector('.logo').src = c.logo || '';
    node.querySelector('.name').textContent = c.name;
    node.querySelector('.symbol').textContent = c.symbol;
    node.querySelector('.price-val').textContent = c.price !== undefined ? Number(c.price).toLocaleString(undefined, {maximumFractionDigits:6}) : '—';
    node.querySelector('.visit').addEventListener('click', ()=>window.open(c.tv_link || '#','_blank'));
    const favBtn = node.querySelector('.fav');
    favBtn.title = 'Favorite';
    favBtn.classList.toggle('active', !!c.fav);
    favBtn.textContent = c.fav ? '★' : '☆';
    favBtn.addEventListener('click', async () => {
      c.fav = !c.fav;
      await api('save', {coins});
      render();
    });
    node.querySelector('.del').addEventListener('click', async () => {
      if(!confirm(`Delete ${c.name}?`)) return;
      coins = coins.filter(x=>x.cg_id !== c.cg_id);
      await api('save', {coins});
      render();
    });

    // drag events
    li.addEventListener('dragstart', (e)=>{
      dragSrc = c.cg_id;
      li.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    li.addEventListener('dragend', ()=>li.classList.remove('dragging'));
    li.addEventListener('dragover', (e)=>e.preventDefault());
    li.addEventListener('drop', async (e)=>{
      e.preventDefault();
      const targetId = li.dataset.id;
      if(!dragSrc || dragSrc === targetId) return;
      const idxFrom = coins.findIndex(x=>x.cg_id===dragSrc);
      const idxTo = coins.findIndex(x=>x.cg_id===targetId);
      if(idxFrom<0||idxTo<0) return;
      const [item] = coins.splice(idxFrom,1);
      coins.splice(idxTo,0,item);
      await api('save', {coins});
      render();
    });

    coinListEl.appendChild(node);
    if (c.price) total += Number(c.price);
  });
  totalValueEl.textContent = total ? Number(total).toLocaleString(undefined, {maximumFractionDigits:2}) : '—';
}

async function load() {
  const res = await api('load');
  if(res.success) {
    coins = res.coins || [];
    render();
  } else {
    coins = [];
  }
}

addBtn.addEventListener('click', async ()=>{
  const link = tvLinkInput.value.trim();
  if(!link) return alert('Paste a TradingView link first.');
  addBtn.disabled = true;
  addBtn.textContent = 'Adding...';
  try {
    const res = await api('add', {tv_link: link});
    if(res.success) {
      coins.unshift(res.coin); // newest on top
      await api('save',{coins});
      tvLinkInput.value = '';
      render();
    } else {
      alert('Add failed: ' + (res.message || 'unknown'));
    }
  } catch(e) {
    alert('Network or server error');
  } finally {
    addBtn.disabled = false;
    addBtn.textContent = 'Add Coin';
  }
});

searchInput.addEventListener('input', render);

async function refreshPrices() {
  if(coins.length===0) return;
  const ids = coins.map(c=>c.cg_id).join(',');
  const res = await api('refresh', {ids});
  if(res.success && res.prices) {
    const map = res.prices;
    coins = coins.map(c=>{
      if(map[c.cg_id] && map[c.cg_id].usd !== undefined) c.price = map[c.cg_id].usd;
      return c;
    });
    await api('save', {coins}); // update saved prices
    render();
  }
}

autorefresh.addEventListener('change', ()=>{
  if(autorefresh.checked) startAuto();
  else stopAuto();
});

function startAuto(){
  stopAuto();
  refreshInterval = setInterval(refreshPrices, 60000);
}
function stopAuto(){
  if(refreshInterval){ clearInterval(refreshInterval); refreshInterval = null; }
}

window.addEventListener('load', async ()=>{
  await load();
  if(autorefresh.checked) startAuto();
  // keep prices fresh on load
  refreshPrices();
});
