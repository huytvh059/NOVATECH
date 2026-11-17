// Les contrôles JavaScript complètent les validations serveur, sans les remplacer.
const menu = document.getElementById('menu-toggle');
menu?.addEventListener('click', () => {
  const expanded = menu.getAttribute('aria-expanded') !== 'true';
  menu.setAttribute('aria-expanded', String(expanded));
  document.getElementById('sidebar').classList.toggle('open', expanded);
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    document.getElementById('sidebar')?.classList.remove('open');
    menu?.setAttribute('aria-expanded', 'false');
  }
});
document.querySelectorAll('.confirm-delete').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm('Xóa bản ghi này? Thao tác không thể hoàn tác.')) event.preventDefault();
}));
document.getElementById('password-toggle')?.addEventListener('click', event => {
  const input = document.getElementById('password');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  event.currentTarget.textContent = show ? 'Ẩn' : 'Hiện';
  event.currentTarget.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
});
