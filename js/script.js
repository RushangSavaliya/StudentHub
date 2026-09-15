(function () {
  /* ===== Dashboard Sidebar Toggle (mobile) ===== */
  var sidebar = document.querySelector(".dashboard-sidebar");
  var overlay = document.querySelector(".sidebar-overlay");

  document.addEventListener("click", function (e) {
    var toggleBtn = e.targekt.closest(".sidebar-toggle-btn");
    if (toggleBtn && sidebar) {
      sidebar.classList.toggle("show");
      if (overlay) overlay.classList.toggle("show");
    }
    if (e.target.classList.contains("sidebar-overlay")) {
      if (sidebar) sidebar.classList.remove("show");
      if (overlay) overlay.classList.remove("show");
    }
  });

  /* Close sidebar when clicking a nav link on mobile */
  if (sidebar) {
    sidebar.addEventListener("click", function (e) {
      if (e.target.closest(".sidebar-nav a") && window.innerWidth < 992) {
        sidebar.classList.remove("show");
        if (overlay) overlay.classList.remove("show");
      }
    });
  }
})();
