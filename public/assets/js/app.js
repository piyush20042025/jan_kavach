/* =====================================================================
   JAN KAVACH - Application JavaScript
   Vanilla ES6 + Bootstrap 5. No jQuery dependency.
   Everything is namespaced under window.JK.
   ===================================================================== */
(function () {
  'use strict';

  const JK = window.JK || (window.JK = {});

  // -------------------------------------------------------------------
  // Configuration injected by the layout
  // -------------------------------------------------------------------
  JK.config = window.JK_CONFIG || { baseUrl: '', csrfToken: '', csrfName: '_token' };

  JK.url = function (path) {
    if (!path) return JK.config.baseUrl;
    if (/^https?:\/\//i.test(path)) return path;
    return JK.config.baseUrl.replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');
  };

  // -------------------------------------------------------------------
  // Small helpers
  // -------------------------------------------------------------------
  const $  = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.prototype.slice.call((root || document).querySelectorAll(sel));

  JK.$ = $;
  JK.$$ = $$;

  JK.escape = function (value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  };

  JK.debounce = function (fn, wait) {
    let timer = null;
    return function () {
      const args = arguments, context = this;
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(context, args), wait || 250);
    };
  };

  JK.number = function (value) {
    const n = Number(value) || 0;
    return n.toLocaleString('en-IN');
  };

  /** Toast-style feedback that does not block the page. */
  JK.notify = function (message, type) {
    let host = $('#jk-toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'jk-toast-host';
      host.className = 'toast-container position-fixed top-0 end-0 p-3';
      host.style.zIndex = '2000';
      document.body.appendChild(host);
    }
    const variant = { success: 'success', error: 'danger', danger: 'danger',
                      warning: 'warning', info: 'info' }[type] || 'primary';

    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML =
      '<div class="d-flex">' +
        '<div class="toast-body">' + JK.escape(message) + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
      '</div>';
    host.appendChild(el);

    const toast = new bootstrap.Toast(el, { delay: 4500 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  };

  // -------------------------------------------------------------------
  // AJAX with the CSRF token attached
  // -------------------------------------------------------------------
  JK.request = function (url, options) {
    options = options || {};
    const headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    }, options.headers || {});

    if (options.method && options.method.toUpperCase() !== 'GET') {
      headers['X-CSRF-TOKEN'] = JK.config.csrfToken;
    }

    return fetch(JK.url(url), Object.assign({ credentials: 'same-origin' }, options, { headers }))
      .then(response => response.json().then(body => {
        if (response.status === 419 || (body && body.csrf)) {
          // The session or token expired - a reload restores both.
          JK.notify('Your session expired. The page will reload.', 'warning');
          setTimeout(() => window.location.reload(), 1600);
          throw new Error('CSRF');
        }
        if (response.status === 401 && body && body.redirect) {
          window.location.href = body.redirect;
          throw new Error('Unauthorised');
        }
        if (!response.ok && !body) {
          throw new Error('Request failed with status ' + response.status);
        }
        return body;
      }))
      .catch(error => {
        if (error && (error.message === 'CSRF' || error.message === 'Unauthorised')) {
          throw error;
        }
        console.error('JK.request failed', url, error);
        throw error;
      });
  };

  JK.get = function (url) {
    return JK.request(url, { method: 'GET' });
  };

  JK.post = function (url, data) {
    const form = new FormData();
    form.append(JK.config.csrfName, JK.config.csrfToken);
    Object.keys(data || {}).forEach(key => {
      const value = data[key];
      if (Array.isArray(value)) {
        value.forEach(v => form.append(key + '[]', v));
      } else if (value !== null && value !== undefined) {
        form.append(key, value);
      }
    });
    return JK.request(url, { method: 'POST', body: form });
  };

  // -------------------------------------------------------------------
  // Sidebar
  // -------------------------------------------------------------------
  JK.initSidebar = function () {
    const toggle = $('#jk-sidebar-toggle');
    const backdrop = $('.jk-backdrop');
    const body = document.body;

    if (toggle) {
      toggle.addEventListener('click', function () {
        if (window.innerWidth < 992) {
          body.classList.toggle('jk-sidebar-open');
        } else {
          body.classList.toggle('jk-sidebar-collapsed');
          try {
            localStorage.setItem('jk.sidebar', body.classList.contains('jk-sidebar-collapsed') ? '1' : '0');
          } catch (e) { /* private mode - the preference simply is not remembered */ }
        }
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', () => body.classList.remove('jk-sidebar-open'));
    }

    // Restore the collapsed preference on desktop.
    try {
      if (localStorage.getItem('jk.sidebar') === '1' && window.innerWidth >= 992) {
        body.classList.add('jk-sidebar-collapsed');
      }
    } catch (e) { /* ignore */ }

    // Close the drawer when a link is followed on a small screen.
    $$('.jk-sidebar a[href]').forEach(link => {
      link.addEventListener('click', function () {
        if (window.innerWidth < 992) body.classList.remove('jk-sidebar-open');
      });
    });

    // Keep the group containing the active link open.
    $$('.jk-sidebar .collapse').forEach(group => {
      if (group.querySelector('.jk-nav-link.active')) {
        group.classList.add('show');
        const trigger = $('[data-bs-target="#' + group.id + '"]');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }
    });
  };

  // -------------------------------------------------------------------
  // Dependent geography dropdowns (State -> District -> Block -> GP -> Village)
  // -------------------------------------------------------------------
  JK.initDependentSelects = function (root) {
    $$('[data-depends-on]', root).forEach(function (select) {
      const parentName = select.dataset.dependsOn;
      const endpoint   = select.dataset.endpoint;
      if (!parentName || !endpoint) return;

      const parent = document.querySelector('[name="' + parentName + '"]');
      if (!parent) return;

      const placeholder = select.dataset.placeholder || 'Select';

      const load = function (parentValue, preselect) {
        if (!parentValue) {
          select.innerHTML = '<option value="">' + JK.escape(placeholder) + '</option>';
          select.disabled = true;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          return;
        }
        select.disabled = true;
        select.innerHTML = '<option value="">Loading...</option>';

        JK.get(endpoint.replace('{id}', encodeURIComponent(parentValue)))
          .then(function (response) {
            const items = (response && response.data) || [];
            let html = '<option value="">' + JK.escape(placeholder) + '</option>';
            items.forEach(function (item) {
              const selected = String(item.id) === String(preselect) ? ' selected' : '';
              html += '<option value="' + item.id + '"' + selected + '>' + JK.escape(item.name) + '</option>';
            });
            select.innerHTML = html;
            select.disabled = items.length === 0;
            if (items.length === 0) {
              select.innerHTML = '<option value="">No records available</option>';
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
          })
          .catch(function () {
            select.innerHTML = '<option value="">Could not load</option>';
            select.disabled = false;
          });
      };

      parent.addEventListener('change', function () {
        load(parent.value, null);
      });

      // On first load, fill the chain when the parent already has a value
      // but this select has not been rendered server-side.
      if (parent.value && select.options.length <= 1 && !select.dataset.serverFilled) {
        load(parent.value, select.dataset.selected || null);
      }
    });
  };

  // -------------------------------------------------------------------
  // Password show / hide
  // -------------------------------------------------------------------
  JK.initPasswordToggles = function (root) {
    $$('.jk-password-toggle', root).forEach(function (button) {
      if (button.dataset.bound) return;
      button.dataset.bound = '1';

      button.addEventListener('click', function () {
        const input = button.parentElement.querySelector('input');
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.innerHTML = '<i class="bi ' + (showing ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });
  };

  // -------------------------------------------------------------------
  // Confirmation before destructive actions
  // -------------------------------------------------------------------
  JK.initConfirms = function (root) {
    $$('[data-confirm]', root).forEach(function (el) {
      if (el.dataset.confirmBound) return;
      el.dataset.confirmBound = '1';

      el.addEventListener('click', function (event) {
        if (!window.confirm(el.dataset.confirm)) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });
  };

  // -------------------------------------------------------------------
  // Auto-submitting filter forms
  // -------------------------------------------------------------------
  JK.initAutoFilters = function (root) {
    $$('[data-auto-submit]', root).forEach(function (el) {
      if (el.dataset.autoBound) return;
      el.dataset.autoBound = '1';

      const form = el.closest('form');
      if (!form) return;

      const submit = function () {
        // Drop the page number so a new filter starts at page one.
        const page = form.querySelector('[name="page"]');
        if (page) page.value = '1';
        form.submit();
      };

      if (el.tagName === 'SELECT' || el.type === 'date' || el.type === 'checkbox') {
        el.addEventListener('change', submit);
      } else {
        el.addEventListener('input', JK.debounce(submit, 600));
      }
    });
  };

  // -------------------------------------------------------------------
  // Guard against a double form submission
  // -------------------------------------------------------------------
  JK.initFormGuards = function (root) {
    $$('form[data-guard]', root).forEach(function (form) {
      if (form.dataset.guardBound) return;
      form.dataset.guardBound = '1';

      form.addEventListener('submit', function () {
        const buttons = $$('button[type="submit"], input[type="submit"]', form);
        setTimeout(function () {
          buttons.forEach(function (button) {
            button.disabled = true;
            if (button.tagName === 'BUTTON' && !button.dataset.keepLabel) {
              button.dataset.original = button.innerHTML;
              button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working...';
            }
          });
        }, 0);

        // Re-enable if the page is restored from the back/forward cache.
        window.addEventListener('pageshow', function () {
          buttons.forEach(function (button) {
            button.disabled = false;
            if (button.dataset.original) button.innerHTML = button.dataset.original;
          });
        });
      });
    });
  };

  // -------------------------------------------------------------------
  // Global scheme search in the header
  // -------------------------------------------------------------------
  // -------------------------------------------------------------------
  // Conditional questionnaire fields
  //
  // A question carrying data-depends-on is shown only while the question it
  // names holds one of the values in data-depends-value. Hidden questions are
  // disabled so the browser skips their validation and the server never
  // receives an answer to a question that was not asked.
  // -------------------------------------------------------------------
  JK.initConditionalQuestions = function (root) {
    const scope = root || document;
    const conditional = $$('.jk-question[data-depends-on]', scope);
    if (!conditional.length) return;

    function valueOf(key) {
      const fields = $$('[data-question-input="' + key + '"], [name$="[' + key + ']"], [name$="[' + key + '][]"]', scope);
      const picked = [];
      fields.forEach(function (field) {
        if (field.type === 'radio' || field.type === 'checkbox') {
          if (field.checked && field.value !== '') picked.push(field.value);
        } else if (field.value !== '' && field.value !== null) {
          picked.push(field.value);
        }
      });
      return picked;
    }

    function refresh() {
      conditional.forEach(function (block) {
        const key = block.dataset.dependsOn;
        const want = (block.dataset.dependsValue || '')
          .split(',').map(function (v) { return v.trim(); }).filter(Boolean);
        const have = valueOf(key);
        const show = want.length === 0
          ? have.length > 0
          : have.some(function (v) { return want.indexOf(v) >= 0; });

        block.hidden = !show;
        $$('input, select, textarea', block).forEach(function (field) {
          field.disabled = !show;
        });
      });
    }

    scope.addEventListener('change', refresh);
    scope.addEventListener('input', refresh);
    refresh();
  };

  JK.initGlobalSearch = function () {
    const input = $('#jk-global-search');
    const panel = $('#jk-search-results');
    if (!input || !panel) return;

    const render = function (payload) {
      const items = (payload && payload.data && payload.data.results) || [];
      if (items.length === 0) {
        panel.innerHTML = '<div class="p-3 text-center text-muted small">No schemes matched that search.</div>';
        panel.classList.add('show');
        return;
      }
      let html = '';
      items.forEach(function (item) {
        const meta = [item.level, item.state, item.theme].filter(Boolean).join(' &middot; ');
        html += '<a class="jk-search-results__item" href="' + item.url + '">' +
                  '<strong>' + JK.escape(item.short || item.name) + '</strong>' +
                  (item.short ? '<span>' + JK.escape(item.name) + '</span>' : '') +
                  '<span>' + meta + '</span>' +
                '</a>';
      });
      const total = (payload.data && payload.data.count) || items.length;
      html += '<div class="jk-search-results__foot">' +
              '<a href="' + JK.url('/search?q=' + encodeURIComponent(input.value)) + '">' +
              'View all ' + total + ' result' + (total === 1 ? '' : 's') + '</a></div>';
      panel.innerHTML = html;
      panel.classList.add('show');
    };

    const search = JK.debounce(function () {
      const term = input.value.trim();
      if (term.length < 2) {
        panel.classList.remove('show');
        return;
      }
      panel.innerHTML = '<div class="p-3 text-center text-muted small">' +
                        '<span class="spinner-border spinner-border-sm me-1"></span>Searching...</div>';
      panel.classList.add('show');

      JK.get('/search?q=' + encodeURIComponent(term)).then(render).catch(function () {
        panel.innerHTML = '<div class="p-3 text-center text-danger small">Search is unavailable right now.</div>';
      });
    }, 320);

    input.addEventListener('input', search);
    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 2 && panel.innerHTML) panel.classList.add('show');
    });

    document.addEventListener('click', function (event) {
      if (!panel.contains(event.target) && event.target !== input) {
        panel.classList.remove('show');
      }
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') panel.classList.remove('show');
      if (event.key === 'Enter') {
        event.preventDefault();
        window.location.href = JK.url('/search?q=' + encodeURIComponent(input.value));
      }
    });
  };

  // -------------------------------------------------------------------
  // Notifications dropdown
  // -------------------------------------------------------------------
  JK.initNotifications = function () {
    const trigger = $('#jk-notif-trigger');
    const list = $('#jk-notif-list');
    if (!trigger || !list) return;

    let loaded = false;

    trigger.addEventListener('click', function () {
      if (loaded) return;
      loaded = true;

      JK.get('/notifications/dropdown').then(function (response) {
        const items = (response.data && response.data.items) || [];
        if (items.length === 0) {
          list.innerHTML = '<div class="p-4 text-center text-muted small">' +
                           '<i class="bi bi-bell-slash d-block mb-2 fs-4"></i>No notifications yet.</div>';
          return;
        }
        let html = '';
        items.forEach(function (item) {
          html += '<a class="jk-notif-item' + (item.seen ? '' : ' unseen') + '" href="' +
                  (item.link || JK.url('/notifications')) + '">' +
                    '<span class="jk-notif-item__icon"><i class="bi ' + JK.escape(item.icon || 'bi-bell') + '"></i></span>' +
                    '<span class="flex-grow-1 min-width-0">' +
                      '<span class="jk-notif-item__title d-block">' + JK.escape(item.title) + '</span>' +
                      (item.message ? '<span class="jk-notif-item__msg d-block">' + JK.escape(item.message) + '</span>' : '') +
                      '<span class="jk-notif-item__time d-block">' + JK.escape(item.ago) + '</span>' +
                    '</span>' +
                  '</a>';
        });
        list.innerHTML = html;
      }).catch(function () {
        list.innerHTML = '<div class="p-3 text-center text-danger small">Notifications could not be loaded.</div>';
      });
    });

    const markAll = $('#jk-notif-mark-all');
    if (markAll) {
      markAll.addEventListener('click', function (event) {
        event.preventDefault();
        JK.post('/notifications/read-all', {}).then(function (response) {
          JK.notify(response.message || 'All notifications marked as read.', 'success');
          const badge = $('#jk-notif-count');
          if (badge) badge.remove();
          $$('.jk-notif-item.unseen').forEach(el => el.classList.remove('unseen'));
        }).catch(function () {
          JK.notify('Could not mark the notifications as read.', 'error');
        });
      });
    }
  };

  // -------------------------------------------------------------------
  // Charts
  // -------------------------------------------------------------------
  JK.charts = {};

  JK.palette = ['#0f4c81', '#2a9d8f', '#e9c46a', '#f4a261', '#e76f51', '#8ecae6',
                '#219ebc', '#6a4c93', '#ffb703', '#fb8500', '#118ab2', '#c9184a',
                '#457b9d', '#83c5be', '#ee9b00', '#9b2226'];

  JK.chartDefaults = function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = '"Segoe UI", Roboto, -apple-system, sans-serif';
    Chart.defaults.font.size = 11.5;
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.labels.boxWidth = 11;
    Chart.defaults.plugins.legend.labels.boxHeight = 11;
    Chart.defaults.plugins.legend.labels.padding = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, .92)';
    Chart.defaults.plugins.tooltip.padding = 9;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 11.5 };
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.animation.duration = 420;
  };

  /**
   * Draw a chart into a canvas, replacing anything already there.
   * Renders a readable empty state rather than a blank box when there
   * is genuinely no data to show.
   */
  JK.drawChart = function (canvasId, type, series, options) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return null;

    const wrap = canvas.parentElement;
    const labels = (series && series.labels) || [];
    const values = (series && series.values) || [];
    const total = values.reduce((sum, v) => sum + (Number(v) || 0), 0);

    const existingEmpty = wrap ? wrap.querySelector('.jk-chart-empty') : null;
    if (existingEmpty) existingEmpty.remove();

    if (labels.length === 0 || total === 0) {
      canvas.style.display = 'none';
      if (wrap) {
        const empty = document.createElement('div');
        empty.className = 'jk-chart-empty';
        empty.innerHTML = '<div><i class="bi bi-bar-chart d-block fs-3 mb-1"></i>' +
                          (options && options.emptyText ? JK.escape(options.emptyText) : 'No data for the selected filters') +
                          '</div>';
        wrap.appendChild(empty);
      }
      if (JK.charts[canvasId]) { JK.charts[canvasId].destroy(); delete JK.charts[canvasId]; }
      return null;
    }
    canvas.style.display = '';

    if (JK.charts[canvasId]) {
      JK.charts[canvasId].destroy();
    }

    // Use a colour supplied by the data where one exists (theme colours,
    // status colours), otherwise fall back to the palette.
    const supplied = (series.colors || []).filter(Boolean);
    const colors = labels.map((_, i) => (series.colors && series.colors[i])
      ? series.colors[i]
      : JK.palette[i % JK.palette.length]);

    const isCircular = type === 'doughnut' || type === 'pie' || type === 'polarArea';
    const horizontal = options && options.horizontal;

    const config = {
      type: type === 'hbar' ? 'bar' : type,
      data: {
        labels: labels,
        datasets: [{
          label: (options && options.label) || 'Count',
          data: values,
          backgroundColor: isCircular ? colors : (options && options.solid ? colors[0] : colors),
          borderColor: isCircular ? '#fff' : colors,
          borderWidth: isCircular ? 2 : 0,
          borderRadius: isCircular ? 0 : 4,
          maxBarThickness: 34,
          fill: type === 'line' ? true : undefined,
          tension: type === 'line' ? 0.32 : undefined,
          pointRadius: type === 'line' ? 3 : undefined,
        }],
      },
      options: {
        responsive: true,
        indexAxis: (type === 'hbar' || horizontal) ? 'y' : 'x',
        plugins: {
          legend: { display: isCircular, position: (options && options.legendPosition) || 'right' },
          tooltip: {
            callbacks: {
              label: function (context) {
                const value = context.parsed[(type === 'hbar' || horizontal) ? 'x' : 'y'] ?? context.parsed;
                const pct = total > 0 ? ' (' + ((value / total) * 100).toFixed(1) + '%)' : '';
                return ' ' + context.label + ': ' + JK.number(value) + (isCircular ? pct : '');
              },
            },
          },
        },
        scales: isCircular ? {} : {
          x: {
            grid: { display: (type === 'hbar' || horizontal), color: '#eef2f7' },
            ticks: { autoSkip: true, maxRotation: 0,
                     callback: function (value, index) {
                       if (type === 'hbar' || horizontal) return JK.number(value);
                       const label = this.getLabelForValue(value);
                       return label && label.length > 15 ? label.substring(0, 14) + '…' : label;
                     } },
          },
          y: {
            beginAtZero: true,
            grid: { display: !(type === 'hbar' || horizontal), color: '#eef2f7' },
            ticks: { precision: 0,
                     callback: function (value) {
                       if (type === 'hbar' || horizontal) {
                         const label = this.getLabelForValue(value);
                         return label && label.length > 22 ? label.substring(0, 21) + '…' : label;
                       }
                       return JK.number(value);
                     } },
          },
        },
      },
    };

    if (options && options.chartOptions) {
      Object.assign(config.options, options.chartOptions);
    }

    JK.charts[canvasId] = new Chart(canvas, config);
    return JK.charts[canvasId];
  };

  /** Multi-series line chart, used by the trend panels. */
  JK.drawMultiLine = function (canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return null;

    const wrap = canvas.parentElement;
    const existingEmpty = wrap ? wrap.querySelector('.jk-chart-empty') : null;
    if (existingEmpty) existingEmpty.remove();

    const hasData = labels.length > 0 &&
      datasets.some(d => (d.data || []).some(v => Number(v) > 0));

    if (!hasData) {
      canvas.style.display = 'none';
      if (wrap) {
        const empty = document.createElement('div');
        empty.className = 'jk-chart-empty';
        empty.innerHTML = '<div><i class="bi bi-graph-up d-block fs-3 mb-1"></i>No trend data yet</div>';
        wrap.appendChild(empty);
      }
      return null;
    }
    canvas.style.display = '';

    if (JK.charts[canvasId]) JK.charts[canvasId].destroy();

    JK.charts[canvasId] = new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: datasets.map(function (set, i) {
          const color = set.color || JK.palette[i % JK.palette.length];
          return {
            label: set.label,
            data: set.data,
            borderColor: color,
            backgroundColor: color + '22',
            fill: set.fill !== false,
            tension: 0.32,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2,
          };
        }),
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'top', align: 'end' } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { precision: 0 } },
        },
      },
    });
    return JK.charts[canvasId];
  };

  // -------------------------------------------------------------------
  // Boot
  // -------------------------------------------------------------------
  JK.init = function () {
    JK.chartDefaults();
    JK.initSidebar();
    JK.initDependentSelects(document);
    JK.initPasswordToggles(document);
    JK.initConfirms(document);
    JK.initAutoFilters(document);
    JK.initFormGuards(document);
    JK.initConditionalQuestions(document);
    JK.initGlobalSearch();
    JK.initNotifications();

    // Bootstrap tooltips wherever they are declared.
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      $$('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    }

    // Dismiss flash alerts after a while, keeping errors on screen.
    $$('.alert-dismissible[data-auto-dismiss]').forEach(function (alert) {
      setTimeout(function () {
        if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
          const instance = bootstrap.Alert.getOrCreateInstance(alert);
          instance.close();
        }
      }, 6500);
    });

    // A date input labelled as an end date cannot precede its start date.
    $$('[data-date-min-from]').forEach(function (input) {
      const source = document.querySelector('[name="' + input.dataset.dateMinFrom + '"]');
      if (!source) return;
      const sync = function () { input.min = source.value || ''; };
      source.addEventListener('change', sync);
      sync();
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', JK.init);
  } else {
    JK.init();
  }
})();
