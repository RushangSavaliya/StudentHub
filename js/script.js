/* ==========================================================================
   StudentHub - Bootstrap 5.3 Integrated Theme & Form Validation
   ========================================================================== */
(function () {
  "use strict";

  const STORAGE_KEY = "studenthub-theme";

  /* ===== 1. Bootstrap 5.3 Theme Switcher ===== */
  function setTheme(theme) {
    document.documentElement.setAttribute("data-bs-theme", theme);
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}

    document.querySelectorAll(".theme-toggle").forEach(btn => {
      const isDark = theme === "dark";
      btn.setAttribute("aria-pressed", String(isDark));
      btn.setAttribute("aria-label", isDark ? "Switch to light theme" : "Switch to dark theme");
      btn.innerHTML = isDark
        ? '<i class="bi bi-sun-fill text-warning"></i> <span class="theme-toggle-text small fw-semibold">Light</span>'
        : '<i class="bi bi-moon-stars-fill text-primary"></i> <span class="theme-toggle-text small fw-semibold">Dark</span>';
    });
  }

  function createThemeToggle() {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-outline-secondary btn-sm theme-toggle d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1";
    btn.addEventListener("click", () => {
      const current = document.documentElement.getAttribute("data-bs-theme") || "light";
      setTheme(current === "dark" ? "light" : "dark");
    });
    return btn;
  }

  function initialiseTheme() {
    let saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    const initialTheme = saved || (prefersDark ? "dark" : "light");

    document.querySelectorAll(".navbar .navbar-collapse > .d-flex").forEach(actions => {
      if (!actions.querySelector(".theme-toggle")) actions.prepend(createThemeToggle());
    });
    document.querySelectorAll(".dashboard-sidebar-container").forEach(sb => {
      if (!sb.querySelector(".theme-toggle")) {
        const wrap = document.createElement("div");
        wrap.className = "mt-auto pt-3 border-top";
        wrap.appendChild(createThemeToggle());
        sb.appendChild(wrap);
      }
    });

    setTheme(initialTheme);
  }

  /* ===== 2. Registration Form Validation ===== */
  function initialiseRegistrationValidation() {
    const form = document.getElementById("registration-form");
    if (!form) return;

    const get = id => form.querySelector("#" + id);
    const scorePw = v => [v.length >= 8, /[a-z]/.test(v) && /[A-Z]/.test(v), /\d/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;

    function updateStrength() {
      const score = scorePw(get("password").value);
      const strEl = form.querySelector(".password-strength");
      if (strEl) strEl.dataset.strength = score;
      const text = get("password-strength-text");
      if (text) text.textContent = "Password strength: " + ["not entered", "weak", "fair", "good", "strong"][score];
      return score;
    }

    function setFieldState(field, key, message) {
      const err = form.querySelector("#" + key + "-error");
      if (err) err.textContent = message;
      if (!field) return !message;
      field.classList.toggle("is-invalid", !!message);
      field.classList.toggle("is-valid", !message && field.value !== "");
      field.setAttribute("aria-invalid", String(!!message));
      return !message;
    }

    const rules = {
      "full-name": () => {
        const v = get("full-name").value.trim();
        return setFieldState(get("full-name"), "full-name", !v ? "Enter your full name." : !/^[A-Za-z]+(?:[ '-][A-Za-z]+)*$/.test(v) ? "Use letters, spaces, apostrophes, or hyphens only." : "");
      },
      "email": () => {
        const v = get("email").value.trim();
        return setFieldState(get("email"), "email", !v ? "Enter your email address." : !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? "Enter a valid email address." : "");
      },
      "mobile": () => {
        const v = get("mobile").value.trim();
        return setFieldState(get("mobile"), "mobile", !v ? "Enter your mobile number." : !/^[6-9]\d{9}$/.test(v) ? "Enter a valid 10-digit Indian mobile number." : "");
      },
      "course": () => setFieldState(get("course"), "course", !get("course").value ? "Select your course." : ""),
      "year":   () => setFieldState(get("year"),   "year",   !get("year").value   ? "Select your academic year." : ""),
      "gender": () => {
        const checked = !!form.querySelector('input[name="gender"]:checked');
        const err = form.querySelector("#gender-error");
        if (err) err.textContent = checked ? "" : "Select your gender.";
        return checked;
      },
      "password": () => {
        const v = get("password").value;
        const score = updateStrength();
        const msg = !v ? "Create a password." : v.length < 8 ? "Use at least 8 characters." : score < 4 ? "Add uppercase, lowercase, number, and special character." : "";
        const ok = setFieldState(get("password"), "password", msg);
        if (get("confirm-password").value) rules["confirm-password"]();
        return ok;
      },
      "confirm-password": () => {
        const v = get("confirm-password").value;
        return setFieldState(get("confirm-password"), "confirm-password", !v ? "Confirm your password." : v !== get("password").value ? "Passwords do not match." : "");
      },
      "terms": () => setFieldState(get("terms"), "terms", get("terms").checked ? "" : "You must accept the terms and conditions.")
    };

    const keys = ["full-name", "email", "mobile", "course", "year", "gender", "password", "confirm-password", "terms"];
    keys.forEach(k => {
      const el = get(k) || form.querySelector(`input[name="${k}"]`);
      if (el) el.addEventListener(k === "terms" ? "change" : "input", () => rules[k]());
    });

    form.addEventListener("submit", e => {
      const ok = keys.every(k => rules[k]());
      if (!ok) {
        e.preventDefault();
        const inv = form.querySelector(".is-invalid");
        if (inv) inv.focus();
      }
    });

    form.addEventListener("reset", () => {
      setTimeout(() => {
        form.querySelectorAll(".is-valid, .is-invalid").forEach(f => { f.classList.remove("is-valid", "is-invalid"); f.removeAttribute("aria-invalid"); });
        form.querySelectorAll(".field-error").forEach(e => { e.textContent = ""; });
        updateStrength();
      });
    });

    updateStrength();
  }

  initialiseTheme();
  initialiseRegistrationValidation();
})();
