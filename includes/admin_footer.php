    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ADMIN_CSRF = <?= json_encode($csrf ?? '') ?>;

async function adminPost(payload) {
    const res = await fetch('../handlers/admin_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, csrf_token: ADMIN_CSRF })
    });
    return await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
}

async function memberAction(payload) {
    try {
        const data = await adminPost(payload);
        alert(data.message || (data.success ? 'Action completed.' : 'Action failed.'));
        if (data.reload) location.reload();
    } catch {
        alert('Connection error. Please try again.');
    }
}

function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

function updateThemeLogos() {
    const isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
    document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
        logo.setAttribute('src', isLight ? logo.dataset.logoLight : logo.dataset.logoDark);
    });
}

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
    updateThemeLogos();
}

(function () {
    const saved = localStorage.getItem('fs-theme');
    if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
    updateThemeLogos();
})();
</script>
</body>
</html>
