      </div><!-- /.container-fluid -->
    </div><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <!-- Footer -->
  <footer class="main-footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <strong>AutoSecForge Pro</strong> &copy; <?= date('Y') ?> &mdash;
        Enterprise Security Orchestration Platform
        <span class="badge badge-secondary ml-1" style="font-size:.65rem;">v12.1</span>
      </div>
      <div class="text-right d-none d-sm-block">
        Powered by
        <i class="fas fa-robot text-indigo mx-1" style="color:var(--asf-indigo);"></i>Ollama AI
        &middot;
        <i class="fab fa-docker mx-1 text-primary"></i>Docker
      </div>
    </div>
  </footer>

</div><!-- /.wrapper -->

<!-- jQuery 3 -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Bootstrap 4 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE 3.2 -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

<script>
// ── Global helpers ─────────────────────────────────────────────────
window.showSpinner = () => { document.getElementById('pageSpinner').style.display = 'flex'; };
window.hideSpinner = () => { document.getElementById('pageSpinner').style.display = 'none'; };

// Active nav highlight (fallback)
document.querySelectorAll('.nav-sidebar .nav-link').forEach(link => {
  if (link.href === window.location.href) link.classList.add('active');
});

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
