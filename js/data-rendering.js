/* StudentHub - Fetch API, JSON rendering, search, filter, sort, and pagination */
(function () {
  "use strict";

  const recordsPerPage = 6;

  async function fetchJson(source) {
    const response = await fetch(source);
    if (!response.ok) throw new Error("The data file could not be loaded.");
    return response.json();
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[character];
    });
  }

  function populateOptions(select, values) {
    values.sort().forEach(function (value) {
      select.insertAdjacentHTML("beforeend", '<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + "</option>");
    });
  }

  function renderPagination(container, page, totalPages, onPageChange) {
    container.innerHTML = "";
    if (totalPages <= 1) return;

    for (let number = 1; number <= totalPages; number += 1) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-outline-sh btn-sm" + (number === page ? " active" : "");
      button.textContent = number;
      button.setAttribute("aria-label", "Go to page " + number);
      button.setAttribute("aria-current", number === page ? "page" : "false");
      button.addEventListener("click", function () { onPageChange(number); });
      container.appendChild(button);
    }
  }

  function showError(app, message) {
    const status = app.querySelector(".data-status");
    status.textContent = message;
    status.classList.add("data-error");
  }

  function formatDate(value) {
    return new Date(value + "T00:00:00").toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
  }

  function initialiseEvents() {
    const app = document.querySelector("[data-events-app]");
    if (!app) return;

    const search = app.querySelector("#event-search");
    const category = app.querySelector("#event-category");
    const sort = app.querySelector("#event-sort");
    const results = app.querySelector("[data-results]");
    const pagination = app.querySelector("[data-pagination]");
    const status = app.querySelector(".data-status");
    let events = [];
    let page = 1;

    function render() {
      const query = search.value.trim().toLowerCase();
      const filtered = events.filter(function (event) {
        return (!query || [event.title, event.location, event.description].join(" ").toLowerCase().includes(query)) &&
          (!category.value || event.category === category.value);
      });
      const sorted = filtered.sort(function (first, second) {
        if (sort.value === "date-desc") return second.date.localeCompare(first.date);
        if (sort.value === "title-asc") return first.title.localeCompare(second.title);
        return first.date.localeCompare(second.date);
      });
      const totalPages = Math.max(1, Math.ceil(sorted.length / recordsPerPage));
      page = Math.min(page, totalPages);
      const visible = sorted.slice((page - 1) * recordsPerPage, page * recordsPerPage);
      status.textContent = sorted.length ? "Showing " + visible.length + " of " + sorted.length + " events." : "No events match your search.";
      results.innerHTML = visible.map(function (event) {
        return '<article class="card h-100"><img class="event-image" src="' + escapeHtml(event.image) + '" alt="' + escapeHtml(event.alt) + '"><div class="card-body"><h5 class="card-title fw-bold">' + escapeHtml(event.title) + '</h5><p class="mb-1"><strong>Date:</strong> ' + formatDate(event.date) + '</p><p class="mb-1"><strong>Time:</strong> ' + escapeHtml(event.time) + '</p><p class="mb-1"><strong>Location:</strong> ' + escapeHtml(event.location) + '</p><p class="card-text">' + escapeHtml(event.description) + '</p><a href="login.html" class="link-btn">Register &rarr;</a></div></article>';
      }).join("");
      renderPagination(pagination, page, totalPages, function (nextPage) { page = nextPage; render(); });
    }

    Promise.resolve(fetchJson(app.dataset.source)).then(function (data) {
      events = data;
      populateOptions(category, Array.from(new Set(events.map(function (event) { return event.category; }))));
      render();
    }).catch(function () { showError(app, "Unable to load events. Please try again later."); });
    [search, category, sort].forEach(function (control) { control.addEventListener("input", function () { page = 1; render(); }); control.addEventListener("change", function () { page = 1; render(); }); });
  }

  function initialiseFaqs() {
    const app = document.querySelector("[data-faqs-app]");
    if (!app) return;

    const search = app.querySelector("#faq-search");
    const category = app.querySelector("#faq-category");
    const sort = app.querySelector("#faq-sort");
    const results = app.querySelector("[data-results]");
    const pagination = app.querySelector("[data-pagination]");
    const status = app.querySelector(".data-status");
    let faqs = [];
    let page = 1;

    function render() {
      const query = search.value.trim().toLowerCase();
      const filtered = faqs.filter(function (faq) {
        return (!query || (faq.question + " " + faq.answer).toLowerCase().includes(query)) && (!category.value || faq.category === category.value);
      });
      const sorted = filtered.sort(function (first, second) { return sort.value === "question-desc" ? second.question.localeCompare(first.question) : first.question.localeCompare(second.question); });
      const totalPages = Math.max(1, Math.ceil(sorted.length / recordsPerPage));
      page = Math.min(page, totalPages);
      const visible = sorted.slice((page - 1) * recordsPerPage, page * recordsPerPage);
      status.textContent = sorted.length ? "Showing " + visible.length + " of " + sorted.length + " questions." : "No questions match your search.";
      results.innerHTML = visible.map(function (faq) {
        const answerId = "faq-answer-" + faq.id;
        return '<article class="faq-item"><h5><button type="button" class="faq-question" aria-expanded="false" aria-controls="' + answerId + '">' + escapeHtml(faq.question) + '<span aria-hidden="true">+</span></button></h5><div class="faq-answer" id="' + answerId + '" hidden><p>' + escapeHtml(faq.answer) + "</p></div></article>";
      }).join("");
      results.querySelectorAll(".faq-question").forEach(function (button) {
        button.addEventListener("click", function () { const answer = document.getElementById(button.getAttribute("aria-controls")); const open = button.getAttribute("aria-expanded") === "true"; button.setAttribute("aria-expanded", String(!open)); answer.hidden = open; });
      });
      renderPagination(pagination, page, totalPages, function (nextPage) { page = nextPage; render(); });
    }

    fetchJson(app.dataset.source).then(function (data) {
      faqs = data;
      populateOptions(category, Array.from(new Set(faqs.map(function (faq) { return faq.category; }))));
      render();
    }).catch(function () { showError(app, "Unable to load FAQs. Please try again later."); });
    [search, category, sort].forEach(function (control) { control.addEventListener("input", function () { page = 1; render(); }); control.addEventListener("change", function () { page = 1; render(); }); });
  }

  function initialiseStudents() {
    const app = document.querySelector("[data-students-app]");
    if (!app) return;

    const search = app.querySelector("#student-search");
    const course = app.querySelector("#course-filter");
    const sort = app.querySelector("#student-sort");
    const results = document.querySelector("tbody[data-results]");
    const pagination = document.querySelector("[data-pagination]");
    const status = app.querySelector(".data-status");
    let students = [];
    let page = 1;

    function render() {
      const query = search.value.trim().toLowerCase();
      const filtered = students.filter(function (student) { return (!query || (student.name + " " + student.email).toLowerCase().includes(query)) && (!course.value || student.course === course.value); });
      const sorted = filtered.sort(function (first, second) {
        if (sort.value === "name-desc") return second.name.localeCompare(first.name);
        if (sort.value === "year-asc") return first.year - second.year;
        return first.name.localeCompare(second.name);
      });
      const totalPages = Math.max(1, Math.ceil(sorted.length / recordsPerPage));
      page = Math.min(page, totalPages);
      const visible = sorted.slice((page - 1) * recordsPerPage, page * recordsPerPage);
      status.textContent = sorted.length ? "Showing " + visible.length + " of " + sorted.length + " student records." : "No students match your search.";
      results.innerHTML = visible.map(function (student) { return "<tr><td>" + escapeHtml(student.id) + "</td><td>" + escapeHtml(student.name) + "</td><td>" + escapeHtml(student.email) + "</td><td>" + escapeHtml(student.course) + "</td><td>" + escapeHtml(student.year) + "</td><td><span class=\"text-secondary\">View | Edit | Delete</span></td></tr>"; }).join("");
      renderPagination(pagination, page, totalPages, function (nextPage) { page = nextPage; render(); });
    }

    fetchJson(app.dataset.source).then(function (data) { students = data; render(); }).catch(function () { showError(app, "Unable to load student records. Please try again later."); });
    app.querySelector("[data-student-filters]").addEventListener("submit", function (event) { event.preventDefault(); });
    [search, course, sort].forEach(function (control) { control.addEventListener("input", function () { page = 1; render(); }); control.addEventListener("change", function () { page = 1; render(); }); });
  }

  initialiseEvents();
  initialiseFaqs();
  initialiseStudents();
})();
