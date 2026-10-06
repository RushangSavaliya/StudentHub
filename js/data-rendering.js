/* StudentHub - Data Rendering Engine using Bootstrap 5.3 Components */
(function () {
  "use strict";

  const PAGE_SIZE = 6;

  async function fetchJson(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error("Data file could not be loaded.");
    return res.json();
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>'"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[c]));
  }

  function renderPagination(container, page, totalPages, onChange) {
    container.innerHTML = "";
    if (totalPages <= 1) return;
    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-outline-secondary btn-sm" + (i === page ? " active" : "");
      btn.textContent = i;
      btn.setAttribute("aria-label", "Page " + i);
      btn.addEventListener("click", () => onChange(i));
      container.appendChild(btn);
    }
  }

  function bindViewer(app, { filterFn, sortFn, renderItem, onData }) {
    if (!app) return;
    const search = app.querySelector("input[type=search]");
    const filter = app.querySelector("select:not([id*='sort'])");
    const sort = app.querySelector("select[id*='sort']");
    const results = app.querySelector("[data-results]");
    const pagination = app.querySelector("[data-pagination]");
    const status = app.querySelector(".data-status");
    let items = [], page = 1;

    function render() {
      const q = (search ? search.value : "").trim().toLowerCase();
      const fVal = filter ? filter.value : "";
      const filtered = items.filter(it => filterFn(it, q, fVal));
      const sorted = filtered.sort((a, b) => sortFn(a, b, sort ? sort.value : ""));
      const totalPages = Math.max(1, Math.ceil(sorted.length / PAGE_SIZE));
      page = Math.min(page, totalPages);
      const visible = sorted.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

      if (status) status.textContent = sorted.length ? `Showing ${visible.length} of ${sorted.length} records.` : "No matching records found.";
      results.innerHTML = visible.map(renderItem).join("");
      if (pagination) renderPagination(pagination, page, totalPages, nextPage => { page = nextPage; render(); });
    }

    fetchJson(app.dataset.source).then(data => {
      items = data;
      if (filter && onData) onData(items, filter);
      render();
    }).catch(() => {
      if (status) { status.textContent = "Unable to load data."; status.classList.add("text-danger"); }
    });

    [search, filter, sort].filter(Boolean).forEach(el => {
      el.addEventListener("input", () => { page = 1; render(); });
      el.addEventListener("change", () => { page = 1; render(); });
    });
    const form = app.querySelector("form");
    if (form) form.addEventListener("submit", e => e.preventDefault());
  }

  // 1. Campus Events View
  bindViewer(document.querySelector("[data-events-app]"), {
    onData: (data, sel) => Array.from(new Set(data.map(d => d.category))).sort().forEach(c => sel.insertAdjacentHTML("beforeend", `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`)),
    filterFn: (ev, q, cat) => (!q || [ev.title, ev.location, ev.description].join(" ").toLowerCase().includes(q)) && (!cat || ev.category === cat),
    sortFn: (a, b, s) => s === "date-desc" ? b.date.localeCompare(a.date) : s === "title-asc" ? a.title.localeCompare(b.title) : a.date.localeCompare(b.date),
    renderItem: ev => `<div class="col">
      <article class="card h-100 shadow-sm border overflow-hidden">
        <img class="event-image border-bottom" src="${escapeHtml(ev.image)}" alt="${escapeHtml(ev.alt)}">
        <div class="card-body p-4 d-flex flex-column">
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle align-self-start mb-2">${escapeHtml(ev.category)}</span>
          <h5 class="card-title fw-bold mb-2">${escapeHtml(ev.title)}</h5>
          <div class="mb-3 text-secondary small">
            <div><i class="bi bi-calendar3 me-1"></i> ${new Date(ev.date + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}</div>
            <div><i class="bi bi-clock me-1"></i> ${escapeHtml(ev.time)}</div>
            <div><i class="bi bi-geo-alt me-1"></i> ${escapeHtml(ev.location)}</div>
          </div>
          <p class="card-text small text-secondary mb-3 flex-grow-1">${escapeHtml(ev.description)}</p>
          <a href="login.html" class="btn btn-primary btn-sm align-self-start">Register &rarr;</a>
        </div>
      </article>
    </div>`
  });

  // 2. FAQs Accordion View
  bindViewer(document.querySelector("[data-faqs-app]"), {
    onData: (data, sel) => Array.from(new Set(data.map(d => d.category))).sort().forEach(c => sel.insertAdjacentHTML("beforeend", `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`)),
    filterFn: (faq, q, cat) => (!q || (faq.question + " " + faq.answer).toLowerCase().includes(q)) && (!cat || faq.category === cat),
    sortFn: (a, b, s) => s === "question-desc" ? b.question.localeCompare(a.question) : a.question.localeCompare(b.question),
    renderItem: faq => `<div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
      <h2 class="accordion-header" id="faq-head-${faq.id}">
        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq-body-${faq.id}" aria-expanded="false" aria-controls="faq-body-${faq.id}">
          ${escapeHtml(faq.question)}
        </button>
      </h2>
      <div id="faq-body-${faq.id}" class="accordion-collapse collapse" aria-labelledby="faq-head-${faq.id}">
        <div class="accordion-body text-secondary">
          ${escapeHtml(faq.answer)}
        </div>
      </div>
    </div>`
  });

  // 3. Students Management Table View
  bindViewer(document.querySelector("[data-students-app]"), {
    filterFn: (st, q, course) => (!q || (st.name + " " + st.email).toLowerCase().includes(q)) && (!course || st.course === course),
    sortFn: (a, b, s) => s === "name-desc" ? b.name.localeCompare(a.name) : s === "year-asc" ? a.year - b.year : a.name.localeCompare(b.name),
    renderItem: st => `<tr>
      <td><span class="badge bg-secondary-subtle text-body border">${escapeHtml(st.id)}</span></td>
      <td class="fw-bold">${escapeHtml(st.name)}</td>
      <td class="text-secondary">${escapeHtml(st.email)}</td>
      <td><span class="badge bg-body-secondary text-body border">${escapeHtml(st.course)}</span></td>
      <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">Year ${escapeHtml(st.year)}</span></td>
      <td>
        <div class="d-flex gap-1">
          <a href="#" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem"><i class="bi bi-eye"></i> View</a>
          <a href="#" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem"><i class="bi bi-pencil"></i> Edit</a>
        </div>
      </td>
    </tr>`
  });
})();
