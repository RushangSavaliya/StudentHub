/* StudentHub - Practical 4 interactivity and Practical 5 form validation */
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

  function initialiseRegistrationValidation() {
    var form = document.getElementById("registration-form");
    if (!form) return;

    var fields = {
      fullName: document.getElementById("full-name"),
      email: document.getElementById("email"),
      mobile: document.getElementById("mobile"),
      course: document.getElementById("course"),
      year: document.getElementById("year"),
      password: document.getElementById("password"),
      confirmPassword: document.getElementById("confirm-password"),
      terms: document.getElementById("terms")
    };
    var genderFields = Array.from(form.querySelectorAll('input[name="gender"]'));
    var namePattern = /^[A-Za-z]+(?:[ '-][A-Za-z]+)*$/;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var mobilePattern = /^[6-9]\d{9}$/;

    function errorElement(key) { return document.getElementById(key + "-error"); }

    function setFieldState(field, key, message) {
      var error = errorElement(key);
      if (error) error.textContent = message || "";
      if (!field) return !message;
      field.classList.toggle("is-invalid", Boolean(message));
      field.classList.toggle("is-valid", !message && field.value !== "");
      field.setAttribute("aria-invalid", String(Boolean(message)));
      return !message;
    }

    function passwordScore(value) {
      var score = 0;
      if (value.length >= 8) score++;
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
      if (/\d/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value)) score++;
      return score;
    }

    function updateStrength() {
      var score = passwordScore(fields.password.value);
      var labels = ["not entered", "weak", "fair", "good", "strong"];
      var meter = form.querySelector(".password-strength");
      meter.dataset.strength = String(score);
      document.getElementById("password-strength-text").textContent = "Password strength: " + labels[score];
      return score;
    }

    function validate(key) {
      var value;
      switch (key) {
        case "full-name":
          value = fields.fullName.value.trim();
          return setFieldState(fields.fullName, key, !value ? "Enter your full name." : !namePattern.test(value) ? "Use letters, spaces, apostrophes, or hyphens only." : "");
        case "email":
          value = fields.email.value.trim();
          return setFieldState(fields.email, key, !value ? "Enter your email address." : !emailPattern.test(value) ? "Enter a valid email address, for example name@example.com." : "");
        case "mobile":
          value = fields.mobile.value.trim();
          return setFieldState(fields.mobile, key, !value ? "Enter your mobile number." : !mobilePattern.test(value) ? "Enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9." : "");
        case "course":
          return setFieldState(fields.course, key, !fields.course.value ? "Select your course." : "");
        case "year":
          return setFieldState(fields.year, key, !fields.year.value ? "Select your academic year." : "");
        case "gender":
          var selectedGender = genderFields.some(function (field) { return field.checked; });
          var genderError = errorElement(key);
          if (genderError) genderError.textContent = selectedGender ? "" : "Select your gender.";
          genderFields.forEach(function (field) { field.setAttribute("aria-invalid", String(!selectedGender)); });
          return selectedGender;
        case "password":
          value = fields.password.value;
          var score = updateStrength();
          var message = !value ? "Create a password." : value.length < 8 ? "Use at least 8 characters." : score < 4 ? "Add uppercase, lowercase, number, and special character to make the password strong." : "";
          var isValid = setFieldState(fields.password, key, message);
          if (fields.confirmPassword.value) validate("confirm-password");
          return isValid;
        case "confirm-password":
          return setFieldState(fields.confirmPassword, key, !fields.confirmPassword.value ? "Confirm your password." : fields.confirmPassword.value !== fields.password.value ? "Passwords do not match." : "");
        case "terms":
          var termsError = errorElement(key);
          var termsMessage = fields.terms.checked ? "" : "You must accept the terms and conditions to register.";
          if (termsError) termsError.textContent = termsMessage;
          fields.terms.classList.toggle("is-invalid", Boolean(termsMessage));
          fields.terms.setAttribute("aria-invalid", String(Boolean(termsMessage)));
          return !termsMessage;
      }
      return true;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var isValid = ["full-name", "email", "mobile", "course", "year", "gender", "password", "confirm-password", "terms"].every(validate);
      if (!isValid) {
        var firstInvalid = form.querySelector(".is-invalid");
        if (firstInvalid) firstInvalid.focus();
      }
    });

    form.addEventListener("reset", function () {
      window.setTimeout(function () {
        form.querySelectorAll(".is-valid, .is-invalid").forEach(function (field) { field.classList.remove("is-valid", "is-invalid"); field.removeAttribute("aria-invalid"); });
        form.querySelectorAll(".field-error").forEach(function (error) { error.textContent = ""; });
        updateStrength();
      });
    });
    updateStrength();
  }

  initialiseNotification();
  initialiseTheme();
  initialiseHamburgerMenu();
  initialiseFaq();
  initialiseSlider();
  initialiseModal();
  initialiseDashboardSidebar();
  initialiseRegistrationValidation();
})();
