    </div>
</div>
<footer class="text-center text-muted py-3 border-top mt-4">
    <small>© <?= date('Y') ?> AutoSecForge Pro – Version 12.0</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
    });
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        if(link.href === window.location.href) link.classList.add('active');
    });
</script>
</body>
</html>
