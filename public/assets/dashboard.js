(function () {
  var word = document.getElementById('greet-word');
  if (word) {
    var h = new Date().getHours();
    word.textContent = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
  }
  document.querySelectorAll('time.when').forEach(function (el) {
    var d = new Date(el.getAttribute('datetime'));
    if (!isNaN(d.getTime())) {
      el.textContent = d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
  });
  document.querySelectorAll('[data-dash-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-dash-open'));
      if (dlg && dlg.showModal) dlg.showModal();
    });
  });
  document.querySelectorAll('[data-dash-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = btn.closest('dialog');
      if (dlg) dlg.close();
    });
  });
})();
