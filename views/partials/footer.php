      </div><!-- /.container-fluid -->
    </div><!-- /.app-content -->
  </main><!-- /.app-main -->

  <!-- Footer -->
  <footer class="app-footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <strong>AutoSecForge Pro</strong> &copy; <?= date('Y') ?> &mdash;
        Enterprise Security Orchestration Platform
        <span class="badge bg-secondary ms-1" style="font-size:.65rem;">v12.1</span>
      </div>
      <div class="text-end d-none d-sm-block">
        Powered by
        <i class="fas fa-robot mx-1" style="color:var(--asf-indigo);"></i>Ollama AI
        &middot;
        <i class="fab fa-docker mx-1 text-primary"></i>Docker
      </div>
    </div>
  </footer>

</div><!-- /.app-wrapper -->

<!-- jQuery 3 (kept: some pages use $ for AJAX; Bootstrap 5 no longer needs it) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Bootstrap 5.3 bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE 4 -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js"></script>

<script>
// ════════════════════════════════════════════════════════════════
//  Bootstrap 4 → 5 data-attribute shim
//  Content pages were written with BS4's data-toggle / data-target /
//  data-dismiss. BS5 reads data-bs-*; its data API resolves attributes
//  at click time via delegation, so mirroring them after load is enough
//  to keep every modal, dropdown, tab and collapse working unchanged.
// ════════════════════════════════════════════════════════════════
(function () {
  var map = { 'data-toggle':'data-bs-toggle', 'data-target':'data-bs-target',
              'data-dismiss':'data-bs-dismiss', 'data-parent':'data-bs-parent',
              'data-slide':'data-bs-slide', 'data-slide-to':'data-bs-slide-to',
              'data-ride':'data-bs-ride' };
  Object.keys(map).forEach(function (from) {
    document.querySelectorAll('[' + from + ']').forEach(function (el) {
      if (!el.hasAttribute(map[from])) el.setAttribute(map[from], el.getAttribute(from));
    });
  });
})();

// ── Global helpers ─────────────────────────────────────────────────
window.showSpinner = () => { document.getElementById('pageSpinner').style.display = 'flex'; };
window.hideSpinner = () => { document.getElementById('pageSpinner').style.display = 'none'; };

// Active nav highlight (fallback)
document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
  if (link.href === window.location.href) link.classList.add('active');
});

// ── Notifications bell ─────────────────────────────────────────────
(function () {
  var badge = document.getElementById('notifBadge');
  var list  = document.getElementById('notifList');
  if (!badge || !list) return;

  var ICONS = { success:'check-circle', warning:'exclamation-triangle',
                danger:'times-circle', info:'info-circle' };
  var COLORS= { success:'#16a34a', warning:'#d97706', danger:'#dc2626', info:'#2563eb' };

  var ENT={'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'};
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g,function(c){return ENT[c];}); }
  function timeAgo(ts){
    var d = new Date((ts||'').replace(' ','T')+'Z'), s=(Date.now()-d.getTime())/1000;
    if (isNaN(s)) return '';
    if (s<60) return 'just now';
    if (s<3600) return Math.floor(s/60)+'m ago';
    if (s<86400) return Math.floor(s/3600)+'h ago';
    return Math.floor(s/86400)+'d ago';
  }

  function render(data){
    var items = (data && data.items) || [];
    var count = (data && data.count) || 0;
    badge.style.display = count > 0 ? '' : 'none';
    badge.textContent   = count > 99 ? '99+' : count;
    if (!items.length){ list.className='px-3 py-2 text-muted small'; list.textContent='No new notifications'; return; }
    list.className = '';
    list.innerHTML = items.map(function(n){
      var t = n.type || 'info', ic = ICONS[t]||ICONS.info, co = COLORS[t]||COLORS.info;
      var unread = String(n.is_read) === '0';
      var inner =
        '<div class="d-flex align-items-start px-3 py-2" style="gap:.6rem;'+(unread?'background:#f8faff;':'')+'">'+
          '<i class="fas fa-'+ic+'" style="color:'+co+';margin-top:.15rem;"></i>'+
          '<div style="flex:1;min-width:0;">'+
            '<div style="font-weight:600;font-size:.8rem;color:#1e293b;">'+esc(n.title)+'</div>'+
            (n.body?'<div class="text-muted" style="font-size:.72rem;">'+esc(n.body)+'</div>':'')+
            '<div class="text-muted" style="font-size:.66rem;">'+timeAgo(n.created_at)+'</div>'+
          '</div>'+
        '</div>';
      return n.link ? '<a href="'+esc(n.link)+'" class="dropdown-item p-0" style="white-space:normal;">'+inner+'</a>' : inner;
    }).join('<div class="dropdown-divider my-0"></div>');
  }

  function poll(){
    fetch('api/notifications.php', {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){ if (d) render(d); })
      .catch(function(){});
  }

  // Mark all read when the bell dropdown opens.
  var bell = badge.closest('.nav-item');
  if (bell) bell.addEventListener('shown.bs.dropdown', function(){
    fetch('api/notifications.php', {method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'read'})}).then(function(){ badge.style.display='none'; });
  });

  poll();
  setInterval(poll, 30000);
})();

// Toast helper
window.toast = function(msg, type = 'success') {
  const id = 'toast_' + Date.now();
  const colors = { success:'#16a34a', danger:'#dc2626', warning:'#d97706', info:'#2563eb' };
  const icons  = { success:'check-circle', danger:'times-circle', warning:'exclamation-triangle', info:'info-circle' };
  const el = document.createElement('div');
  el.id = id;
  el.style.cssText = `
    position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;
    background:#fff;border-left:4px solid ${colors[type]};
    border-radius:.75rem;box-shadow:0 8px 24px rgba(0,0,0,.15);
    padding:.85rem 1.2rem;min-width:260px;max-width:380px;
    display:flex;align-items:center;gap:.75rem;
    animation:slideInRight .3s ease;
  `;
  el.innerHTML = `<i class="fas fa-${icons[type]}" style="color:${colors[type]};font-size:1.1rem;"></i>
    <span style="font-size:.84rem;color:#1e293b;flex:1;">${msg}</span>
    <button onclick="this.closest('div').remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;">&times;</button>`;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 5000);
};
</script>
<style>
@keyframes slideInRight {
  from { opacity:0; transform:translateX(2rem); }
  to   { opacity:1; transform:translateX(0); }
}
</style>

<!-- Page-specific scripts injected here -->
<?php if (!empty($page_scripts)) echo $page_scripts; ?>

</body>
</html>
