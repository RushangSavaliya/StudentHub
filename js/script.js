/* StudentHub - Practical 4: DOM manipulation and UI interactivity */
(function () {
  "use strict";

  var themeStorageKey = "studenthub-theme";
  var focusBeforeModal;

  function setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    // A file opened directly in a browser may block localStorage. Theme saving
    // should not prevent the rest of the Practical 4 interactions from working.
    try {
      localStorage.setItem(themeStorageKey, theme);
    } catch (error) {
      // The selected theme still works for the current page session.
    }

    document.querySelectorAll(".theme-toggle").forEach(function (button) {
      var isDark = theme === "dark";
      button.setAttribute("aria-pressed", String(isDark));
      button.setAttribute("aria-label", isDark ? "Switch to light theme" : "Switch to dark theme");
      button.querySelector(".theme-toggle-text").textContent = isDark ? "Light mode" : "Dark mode";
    });
  }

  function createThemeToggle() {
    var button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-outline-sh btn-sm theme-toggle";
    button.innerHTML = '<span aria-hidden="true">◐</span> <span class="theme-toggle-text">Dark mode</span>';
    button.addEventListener("click", function () {
      setTheme(document.documentElement.dataset.theme === "dark" ? "light" : "dark");
    });
    return button;
  }

  function initialiseTheme() {
    var savedTheme = null;
    try {
      savedTheme = localStorage.getItem(themeStorageKey);
    } catch (error) {
      // Continue without a saved preference if browser storage is unavailable.
    }
    var systemPrefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    var initialTheme = savedTheme || (systemPrefersDark ? "dark" : "light");

    document.querySelectorAll(".navbar").forEach(function (navbar) {
      var actions = navbar.querySelector(".navbar-collapse > .d-flex");
      if (actions && !actions.querySelector(".theme-toggle")) actions.prepend(createThemeToggle());
    });

    setTheme(initialTheme);
  }

  function initialiseHamburgerMenu() {
    document.querySelectorAll(".navbar-toggler").forEach(function (button) {
      var targetId = button.getAttribute("aria-controls");
      var menu = targetId ? document.getElementById(targetId) : null;
      if (!menu) return;

      button.addEventListener("click", function (event) {
        // Explicit DOM/event handler for the Practical 4 hamburger-menu requirement.
        event.preventDefault();
        event.stopPropagation();
        var isOpen = menu.classList.toggle("show");
        button.setAttribute("aria-expanded", String(isOpen));
      });

      menu.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", function () {
          if (window.innerWidth < 992) {
            menu.classList.remove("show");
            button.setAttribute("aria-expanded", "false");
          }
        });
      });
    });
  }

  function initialiseNotification() {
    document.querySelectorAll("[data-notification-dismiss]").forEach(function (button) {
      button.addEventListener("click", function () {
        var notification = button.closest(".notification-bar");
        if (notification) {
          notification.hidden = true;
          // Bootstrap's .d-flex uses !important, so use the same priority when
          // hiding this flex container.
          notification.style.setProperty("display", "none", "important");
        }
      });
    });
  }

  function initialiseFaq() {
    document.querySelectorAll("[data-faq-toggle]").forEach(function (button) {
      var answer = document.getElementById(button.getAttribute("aria-controls"));
      if (!answer) return;

      button.addEventListener("click", function () {
        var isOpen = button.getAttribute("aria-expanded") === "true";
        button.setAttribute("aria-expanded", String(!isOpen));
        answer.hidden = isOpen;
      });
    });
  }

  function initialiseSlider() {
    document.querySelectorAll("[data-slider]").forEach(function (slider) {
      var slides = Array.from(slider.querySelectorAll(".event-slide"));
      var status = slider.querySelector("[data-slider-status]");
      var currentSlide = 0;
      if (!slides.length) return;

      function showSlide(index) {
        currentSlide = (index + slides.length) % slides.length;
        slides.forEach(function (slide, slideIndex) {
          var isActive = slideIndex === currentSlide;
          slide.hidden = !isActive;
          slide.setAttribute("aria-hidden", String(!isActive));
        });
        if (status) status.textContent = "Showing event " + (currentSlide + 1) + " of " + slides.length;
      }

      slider.querySelectorAll("[data-slide-direction]").forEach(function (button) {
        button.addEventListener("click", function () {
          showSlide(currentSlide + Number(button.dataset.slideDirection));
        });
      });

      slider.addEventListener("keydown", function (event) {
        if (event.key === "ArrowLeft") showSlide(currentSlide - 1);
        if (event.key === "ArrowRight") showSlide(currentSlide + 1);
      });

      showSlide(0);
    });
  }

  function closeModal(modal) {
    modal.hidden = true;
    document.body.classList.remove("modal-open");
    if (focusBeforeModal) focusBeforeModal.focus();
  }

  function initialiseModal() {
    document.querySelectorAll("[data-modal-open]").forEach(function (button) {
      var modal = document.getElementById(button.dataset.modalOpen);
      if (!modal) return;

      button.addEventListener("click", function () {
        focusBeforeModal = button;
        modal.hidden = false;
        document.body.classList.add("modal-open");
        var closeButton = modal.querySelector("[data-modal-close]");
        if (closeButton) closeButton.focus();
      });

      modal.querySelectorAll("[data-modal-close]").forEach(function (closeButton) {
        closeButton.addEventListener("click", function () { closeModal(modal); });
      });

      modal.addEventListener("click", function (event) {
        if (event.target === modal) closeModal(modal);
      });
    });

    document.addEventListener("keydown", function (event) {
      var activeModal = document.querySelector(".sh-modal:not([hidden])");
      if (activeModal && event.key === "Escape") closeModal(activeModal);
    });
  }

  function initialiseDashboardSidebar() {
    var sidebar = document.querySelector(".dashboard-sidebar");
    var overlay = document.querySelector(".sidebar-overlay");

    document.addEventListener("click", function (event) {
      var toggleButton = event.target.closest(".sidebar-toggle-btn");
      if (toggleButton && sidebar) {
        sidebar.classList.toggle("show");
        if (overlay) overlay.classList.toggle("show");
      }
      if (event.target.classList.contains("sidebar-overlay")) {
        if (sidebar) sidebar.classList.remove("show");
        if (overlay) overlay.classList.remove("show");
      }
    });

    if (sidebar) {
      sidebar.addEventListener("click", function (event) {
        if (event.target.closest(".sidebar-nav a") && window.innerWidth < 992) {
          sidebar.classList.remove("show");
          if (overlay) overlay.classList.remove("show");
        }
      });
    }
  }

  initialiseNotification();
  initialiseTheme();
  initialiseHamburgerMenu();
  initialiseFaq();
  initialiseSlider();
  initialiseModal();
  initialiseDashboardSidebar();
})();
