<?php
require_once '../src/auth.php';
require_auth();

$page_title = 'Ideas & Feedback';
$is_manager = in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true);
$me         = (int)($_SESSION['user_id'] ?? 0);

require_once '../views/partials/header.php';
?>

<div id="pageActions">
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newIdeaModal">
    <i class="fas fa-plus me-1"></i>New Post
  </button>
</div>

<!-- Stat cards -->
<div class="row" id="ideaStats">
  <?php
  $cards = [
    ['kTotal','Total Posts','fa-comments','#6366f1'],
    ['kOpen','Open','fa-folder-open','#64748b'],
    ['kPlanned','Planned','fa-clipboard-list','#0ea5e9'],
    ['kDone','Done','fa-circle-check','#16a34a'],
  ];
  foreach ($cards as [$id,$label,$icon,$col]): ?>
  <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body d-flex align-items-center">
    <div style="width:42px;height:42px;border-radius:.7rem;background:<?=$col?>1a;display:flex;align-items:center;justify-content:center;margin-right:.75rem;"><i class="fas <?=$icon?>" style="color:<?=$col?>;"></i></div>
    <div><div id="<?=$id?>" style="font-size:1.4rem;font-weight:800;color:#1e293b;line-height:1;">0</div><div class="text-muted" style="font-size:.72rem;"><?=$label?></div></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
      <div class="position-relative" style="flex:1 1 220px;max-width:320px;">
        <i class="fas fa-search position-absolute text-muted" style="left:.7rem;top:50%;transform:translateY(-50%);font-size:.8rem;"></i>
        <input type="text" id="ideaSearch" class="form-control form-control-sm" placeholder="Search posts…" style="padding-left:2rem;border-radius:.5rem;">
      </div>
      <div class="btn-group btn-group-sm" role="group" id="sortToggle">
        <button class="btn btn-outline-secondary active" data-sort="top"><i class="fas fa-fire me-1"></i>Top</button>
        <button class="btn btn-outline-secondary" data-sort="new"><i class="fas fa-clock me-1"></i>New</button>
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center mt-2" style="gap:.5rem;">
      <div class="btn-group btn-group-sm" role="group" id="statusFilter">
        <button class="btn btn-outline-secondary active" data-status="">All</button>
        <button class="btn btn-outline-secondary" data-status="open">Open</button>
        <button class="btn btn-outline-secondary" data-status="planned">Planned</button>
        <button class="btn btn-outline-secondary" data-status="in_progress">In Progress</button>
        <button class="btn btn-outline-secondary" data-status="done">Done</button>
      </div>
      <div class="btn-group btn-group-sm ms-auto" role="group" id="catFilter">
        <button class="btn btn-outline-secondary active" data-cat="">All types</button>
        <button class="btn btn-outline-secondary" data-cat="idea"><i class="fas fa-lightbulb me-1" style="color:#6366f1;"></i>Ideas</button>
        <button class="btn btn-outline-secondary" data-cat="feedback"><i class="fas fa-comment-dots me-1" style="color:#0ea5e9;"></i>Feedback</button>
        <button class="btn btn-outline-secondary" data-cat="bug"><i class="fas fa-bug me-1" style="color:#dc2626;"></i>Bugs</button>
        <button class="btn btn-outline-secondary" data-cat="question"><i class="fas fa-circle-question me-1" style="color:#d97706;"></i>Q&amp;A</button>
      </div>
    </div>
  </div>
</div>

<div id="ideaList"><div class="text-muted small p-3">Loading…</div></div>

<!-- New idea modal -->
<div class="modal fade" id="newIdeaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border:none;border-radius:1rem;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-lightbulb me-2" style="color:var(--asf-indigo);"></i>Share an idea or feedback</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-bold">Category</label>
          <select class="form-select" id="ideaCategory">
            <option value="idea">💡 Idea</option>
            <option value="feedback">💬 Feedback</option>
            <option value="bug">🐞 Bug</option>
            <option value="question">❓ Question</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Title</label>
          <input type="text" class="form-control" id="ideaTitle" maxlength="200" placeholder="A short, clear summary">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">Details <span class="text-muted fw-normal">(optional)</span></label>
          <textarea class="form-control" id="ideaBody" rows="4" placeholder="Describe the idea, the problem, or your feedback…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="submitIdea"><i class="fas fa-paper-plane me-1"></i>Post</button>
      </div>
    </div>
  </div>
</div>

<?php
$boot = json_encode(['isManager' => $is_manager, 'me' => $me, 'openId' => isset($_GET['id']) ? (int)$_GET['id'] : 0]);
$page_scripts = <<<JS
<script>
const ASF = $boot;
let curStatus = '', curCat = '', curSort = 'top', curSearch = '';
let allItems = [];

const CAT = {
  idea:{i:'lightbulb',c:'#6366f1',l:'Idea'}, feedback:{i:'comment-dots',c:'#0ea5e9',l:'Feedback'},
  bug:{i:'bug',c:'#dc2626',l:'Bug'}, question:{i:'circle-question',c:'#d97706',l:'Question'}
};
const ST = {
  open:{l:'Open',c:'#64748b'}, planned:{l:'Planned',c:'#6366f1'},
  in_progress:{l:'In Progress',c:'#0ea5e9'}, done:{l:'Done',c:'#16a34a'}, declined:{l:'Declined',c:'#94a3b8'}
};
const STATUSES = ['open','planned','in_progress','done','declined'];
const ENT={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'};
const esc = s => (s==null?'':String(s)).replace(/[&<>"']/g, c => ENT[c]);

function ago(ts){
  const d=new Date((ts||'').replace(' ','T')+'Z'), s=(Date.now()-d.getTime())/1000;
  if(isNaN(s))return''; if(s<60)return'just now'; if(s<3600)return Math.floor(s/60)+'m ago';
  if(s<86400)return Math.floor(s/3600)+'h ago'; return Math.floor(s/86400)+'d ago';
}

function post(body){
  return fetch('api/ideas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
    .then(r=>r.json());
}

function card(it){
  const cat = CAT[it.category]||CAT.idea, st = ST[it.status]||ST.open;
  const voted = String(it.my_vote)==='1';
  const canManage = ASF.isManager;
  const canDelete = ASF.isManager || String(it.author_id)===String(ASF.me);
  let statusCtrl;
  if (canManage){
    statusCtrl = '<select class="form-select form-select-sm asf-status" data-id="'+it.id+'" style="width:auto;font-size:.72rem;">'+
      STATUSES.map(s=>'<option value="'+s+'"'+(s===it.status?' selected':'')+'>'+ST[s].l+'</option>').join('')+'</select>';
  } else {
    statusCtrl = '<span class="badge" style="background:'+st.c+'1a;color:'+st.c+';">'+st.l+'</span>';
  }
  return '<div class="card mb-2"><div class="card-body d-flex" style="gap:.9rem;">'+
    '<button class="btn btn-sm asf-vote d-flex flex-column align-items-center justify-content-center" data-id="'+it.id+'" '+
      'style="min-width:54px;border:1px solid '+(voted?'#6366f1':'#e2e8f0')+';border-radius:.6rem;'+
      (voted?'background:#eef2ff;color:#6366f1;':'color:#64748b;')+'">'+
      '<i class="fas fa-caret-up" style="font-size:1.1rem;"></i><span style="font-weight:800;font-size:.95rem;">'+it.votes+'</span></button>'+
    '<div style="flex:1;min-width:0;">'+
      '<div class="d-flex align-items-center mb-1" style="gap:.5rem;flex-wrap:wrap;">'+
        '<i class="fas fa-'+cat.i+'" style="color:'+cat.c+';"></i>'+
        '<a href="ideas.php?id='+it.id+'" style="font-weight:700;color:#1e293b;text-decoration:none;">'+esc(it.title)+'</a>'+
        statusCtrl+
      '</div>'+
      (it.body?'<div class="text-muted" style="font-size:.82rem;white-space:pre-wrap;">'+esc(it.body)+'</div>':'')+
      '<div class="text-muted mt-1" style="font-size:.7rem;">'+esc(it.author||'Unknown')+' · '+ago(it.created_at)+
        ' · <a href="ideas.php?id='+it.id+'" style="color:#6366f1;text-decoration:none;"><i class="fas fa-comment me-1"></i>'+it.comments+'</a>'+
        (canDelete?' · <a href="#" class="asf-del text-danger" data-id="'+it.id+'">Delete</a>':'')+
      '</div>'+
    '</div></div></div>';
}

function updateStats(){
  const by = s => allItems.filter(i=>i.status===s).length;
  document.getElementById('kTotal').textContent   = allItems.length;
  document.getElementById('kOpen').textContent    = by('open');
  document.getElementById('kPlanned').textContent = by('planned') + by('in_progress');
  document.getElementById('kDone').textContent    = by('done');
}

function applyFilters(){
  const el = document.getElementById('ideaList');
  let items = allItems.slice();
  if (curStatus) items = items.filter(i=>i.status===curStatus);
  if (curCat)    items = items.filter(i=>i.category===curCat);
  if (curSearch){
    const q = curSearch.toLowerCase();
    items = items.filter(i=>((i.title||'')+' '+(i.body||'')+' '+(i.author||'')).toLowerCase().includes(q));
  }
  if (curSort==='new') items.sort((a,b)=>(b.created_at||'').localeCompare(a.created_at||''));
  else items.sort((a,b)=>(+b.votes - +a.votes) || (b.created_at||'').localeCompare(a.created_at||''));

  if (!items.length){
    const empty = allItems.length ? 'No posts match your filters.' : 'No posts yet. Be the first to share an idea.';
    el.innerHTML='<div class="card"><div class="card-body text-center text-muted py-5">'+
      '<i class="fas fa-lightbulb mb-2" style="font-size:1.6rem;color:#cbd5e1;"></i><div>'+empty+'</div></div></div>';
    return;
  }
  el.innerHTML = items.map(card).join('');
}

function load(){
  fetch('api/ideas.php').then(r=>r.json()).then(d=>{
    allItems = (d&&d.items)||[];
    updateStats();
    applyFilters();
  });
}

function refresh(){ if (ASF.openId) loadDetail(ASF.openId); else load(); }

// Event delegation for vote / status / delete
document.getElementById('ideaList').addEventListener('click', e=>{
  const v = e.target.closest('.asf-vote');
  if (v){ post({action:'vote', idea_id:+v.dataset.id}).then(refresh); return; }
  const d = e.target.closest('.asf-del');
  if (d){ e.preventDefault(); if(confirm('Delete this post?'))
    post({action:'delete', idea_id:+d.dataset.id}).then(()=>{ ASF.openId ? window.location='ideas.php' : load(); }); return; }
});
document.getElementById('ideaList').addEventListener('change', e=>{
  const s = e.target.closest('.asf-status');
  if (s){ post({action:'status', idea_id:+s.dataset.id, status:s.value}).then(()=>{ if(window.toast) toast('Status updated','success'); load(); }); }
});

// Filters (client-side — no refetch)
function wireGroup(id, attr, set){
  document.getElementById(id).addEventListener('click', e=>{
    const b=e.target.closest('button'); if(!b)return;
    document.querySelectorAll('#'+id+' button').forEach(x=>x.classList.remove('active'));
    b.classList.add('active'); set(b.dataset[attr]); applyFilters();
  });
}
wireGroup('statusFilter','status', v=>curStatus=v);
wireGroup('catFilter','cat',      v=>curCat=v);
wireGroup('sortToggle','sort',    v=>curSort=v);

let searchTimer;
document.getElementById('ideaSearch').addEventListener('input', e=>{
  clearTimeout(searchTimer);
  searchTimer = setTimeout(()=>{ curSearch=e.target.value.trim(); applyFilters(); }, 180);
});

// Submit new idea
document.getElementById('submitIdea').addEventListener('click', ()=>{
  const title=document.getElementById('ideaTitle').value.trim();
  if(!title){ if(window.toast)toast('Title is required','warning'); return; }
  post({action:'create', title, body:document.getElementById('ideaBody').value,
        category:document.getElementById('ideaCategory').value}).then(r=>{
    if(r&&r.ok){
      bootstrap.Modal.getInstance(document.getElementById('newIdeaModal')).hide();
      document.getElementById('ideaTitle').value=''; document.getElementById('ideaBody').value='';
      if(window.toast)toast('Posted — thanks for the input!','success'); load();
    } else if(window.toast) toast((r&&r.error)||'Failed to post','danger');
  });
});

// ── Detail view (single idea + comments) ───────────────────────────
function loadDetail(id){
  fetch('api/ideas.php?id='+id).then(r=>r.json()).then(d=>{
    const el = document.getElementById('ideaList');
    if (!d || !d.idea){ el.innerHTML='<div class="card"><div class="card-body text-muted">Post not found.</div></div>'; return; }
    const it=d.idea, cat=CAT[it.category]||CAT.idea, st=ST[it.status]||ST.open, voted=String(it.my_vote)==='1';
    const comments=(d.comments||[]).map(c=>
      '<div class="d-flex mb-3" style="gap:.6rem;">'+
        '<div style="width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;color:#6366f1;font-weight:700;flex-shrink:0;">'+esc((c.author||'?').charAt(0).toUpperCase())+'</div>'+
        '<div><div style="font-size:.8rem;"><b>'+esc(c.author||'Unknown')+'</b> <span class="text-muted">'+ago(c.created_at)+'</span></div>'+
        '<div style="font-size:.85rem;white-space:pre-wrap;">'+esc(c.body)+'</div></div>'+
      '</div>').join('') || '<div class="text-muted small">No comments yet.</div>';
    el.innerHTML =
      '<a href="ideas.php" class="btn btn-sm btn-light mb-2"><i class="fas fa-arrow-left me-1"></i>Back</a>'+
      '<div class="card mb-3"><div class="card-body d-flex" style="gap:.9rem;">'+
        '<button class="btn btn-sm asf-vote d-flex flex-column align-items-center justify-content-center" data-id="'+it.id+'" '+
          'style="min-width:54px;border:1px solid '+(voted?'#6366f1':'#e2e8f0')+';border-radius:.6rem;'+(voted?'background:#eef2ff;color:#6366f1;':'color:#64748b;')+'">'+
          '<i class="fas fa-caret-up" style="font-size:1.1rem;"></i><span style="font-weight:800;">'+it.votes+'</span></button>'+
        '<div style="flex:1;"><div class="d-flex align-items-center mb-1" style="gap:.5rem;flex-wrap:wrap;">'+
          '<i class="fas fa-'+cat.i+'" style="color:'+cat.c+';"></i><h5 class="mb-0">'+esc(it.title)+'</h5>'+
          '<span class="badge" style="background:'+st.c+'1a;color:'+st.c+';">'+st.l+'</span></div>'+
          (it.body?'<div style="white-space:pre-wrap;">'+esc(it.body)+'</div>':'')+
          '<div class="text-muted mt-1" style="font-size:.72rem;">'+esc(it.author||'Unknown')+' · '+ago(it.created_at)+'</div>'+
        '</div></div></div>'+
      '<div class="card"><div class="card-header"><span class="card-title">Comments</span></div><div class="card-body">'+
        comments+
        '<div class="mt-3"><textarea class="form-control mb-2" id="cBody" rows="2" placeholder="Add a comment…"></textarea>'+
        '<button class="btn btn-sm btn-primary" id="cSend"><i class="fas fa-paper-plane me-1"></i>Comment</button></div>'+
      '</div></div>';
    document.getElementById('cSend').addEventListener('click', ()=>{
      const body=document.getElementById('cBody').value.trim();
      if(!body){ if(window.toast)toast('Write a comment first','warning'); return; }
      post({action:'comment', idea_id:id, body}).then(r=>{ if(r&&r.ok) loadDetail(id); });
    });
  });
}

if (ASF.openId) loadDetail(ASF.openId); else load();
</script>
JS;

require_once '../views/partials/footer.php';
