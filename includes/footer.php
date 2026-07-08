<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Popup de permissões na navbar — abre ao clicar no badge do role, fecha ao clicar fora
function togglePermsBox(e) {
    e.stopPropagation();
    const box = document.getElementById('perms-box');
    if (box) box.style.display = box.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', () => {
    const box = document.getElementById('perms-box');
    if (box) box.style.display = 'none';
});
</script>
</body>
</html>
