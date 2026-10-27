{{-- Pasang tema sebelum render pertama agar tidak berkedip. Pilihan tersimpan; default mengikuti sistem. --}}
<script>
  (function () {
    var t = null;
    try { t = localStorage.getItem('notifora_theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') {
      t = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
