/**
 * Proyección personal — ingresos/egresos mensuales + KPIs vs patrimonio real.
 */
(function () {
  'use strict';

  const root = document.getElementById('pp-root');
  if (!root) return;

  const readOnly = root.dataset.readonly === '1';
  const currency = (root.dataset.currency || 'ARS').toUpperCase();
  const payloadEl = document.getElementById('pp-workbook');
  const saveUrl = root.dataset.saveUrl || '';
  const csrf = root.dataset.csrf || '';
  const statusEl = document.getElementById('pp-status');
  const yearTabs = document.getElementById('pp-year-tabs');
  const kpiEl = document.getElementById('pp-kpis');
  const incomeBody = document.getElementById('pp-income-body');
  const expensesBody = document.getElementById('pp-expenses-body');
  const summaryBody = document.getElementById('pp-summary-body');
  const rateInput = document.getElementById('pp-savings-rate');
  const btnSave = document.getElementById('pp-save');
  const btnAddIncome = document.getElementById('pp-add-income');
  const btnAddExpense = document.getElementById('pp-add-expense');
  const btnAddYear = document.getElementById('pp-add-year');
  const flowChart = document.getElementById('pp-flow-chart');
  const cumChartEl = document.getElementById('pp-cum-chart');
  const breakChartEl = document.getElementById('pp-break-chart');
  const burdenMonthsEl = document.getElementById('pp-burden-months');
  const burdenTotalEl = document.getElementById('pp-burden-total');
  const burdenCategoriesEl = document.getElementById('pp-burden-categories');
  const burdenKpiValue = document.getElementById('pp-burden-kpi-value');
  const dailyMonthsEl = document.getElementById('pp-daily-months');
  const dailyKpiValue = document.getElementById('pp-daily-kpi-value');
  const dailyMonthCards = document.getElementById('pp-daily-month-cards');
  const reality = (() => {
    try {
      return JSON.parse(document.getElementById('pp-reality')?.textContent || '{}');
    } catch (_) {
      return {};
    }
  })();

  const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

  let workbook;
  try {
    workbook = JSON.parse(payloadEl?.textContent || '{}');
  } catch (_) {
    workbook = {};
  }
  if (!Array.isArray(workbook.years) || !workbook.years.length) {
    const y = new Date().getFullYear();
    workbook = {
      version: 2,
      template: 'personal',
      activeYear: 1,
      years: [emptyYear(String(y - 1)), emptyYear(String(y)), emptyYear(String(y + 1))],
    };
  }
  workbook.template = 'personal';

  function indexForCurrentCalendarYear(years) {
    const now = new Date().getFullYear();
    let exact = -1;
    let closest = 0;
    let closestDist = Infinity;
    (years || []).forEach((y, i) => {
      const n = parseInt(String(y?.name || ''), 10);
      if (!Number.isFinite(n)) return;
      if (n === now) exact = i;
      const dist = Math.abs(n - now);
      if (dist < closestDist) {
        closestDist = dist;
        closest = i;
      }
    });
    if (exact >= 0) return exact;
    if ((years || []).length) return closest;
    return 0;
  }

  let active = clamp(indexForCurrentCalendarYear(workbook.years), 0, Math.max(0, workbook.years.length - 1));
  workbook.activeYear = active;
  let dirty = false;
  const charts = {
    flow: null,
    cum: null,
    break: null,
    burdenMonths: null,
    burdenTotal: null,
    burdenCategories: null,
    dailyMonths: null,
  };

  function emptyMonths() {
    return Array(12).fill(0);
  }
  function emptyLine(name) {
    return { name: name || '', months: emptyMonths() };
  }
  function emptyYear(name) {
    return {
      name,
      savings_rate: 0.1,
      income: [emptyLine('Sueldo / honorarios')],
      expenses: [emptyLine('Gastos fijos')],
    };
  }
  function clamp(n, a, b) {
    return Math.max(a, Math.min(b, n));
  }
  function year() {
    return workbook.years[active];
  }
  function parseNum(v) {
    if (typeof v === 'number') return v;
    const s = String(v || '').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }
  function formatMoney(v) {
    try {
      return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v || 0));
    } catch (_) {
      return String(Math.round(v || 0));
    }
  }
  function moneyLabel(v) {
    return currency + ' ' + formatMoney(v);
  }
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function formatMoneyDec(v) {
    try {
      return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v || 0);
    } catch (_) {
      return String(v || 0);
    }
  }
  function setStatus(msg, kind) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'fp-status' + (kind ? ' is-' + kind : '');
  }
  function markDirty() {
    if (readOnly) return;
    dirty = true;
    setStatus('Sin guardar', 'warn');
  }

  function statsOf(y) {
    const incomeM = emptyMonths();
    const expenseM = emptyMonths();
    (y.income || []).forEach((row) => {
      for (let i = 0; i < 12; i++) incomeM[i] += Number(row.months?.[i]) || 0;
    });
    (y.expenses || []).forEach((row) => {
      for (let i = 0; i < 12; i++) expenseM[i] += Number(row.months?.[i]) || 0;
    });
    const rate = Number(y.savings_rate) || 0;
    const balanceM = incomeM.map((v, i) => v - expenseM[i]);
    const savingsM = balanceM.map((v) => (v > 0 ? v * rate : 0));
    const profitM = balanceM.map((v, i) => v - savingsM[i]);
    const sum = (arr) => arr.reduce((a, b) => a + b, 0);
    return {
      incomeM,
      expenseM,
      balanceM,
      savingsM,
      profitM,
      incomeAnnual: sum(incomeM),
      expenseAnnual: sum(expenseM),
      balanceAnnual: sum(balanceM),
      savingsAnnual: sum(savingsM),
      profitAnnual: sum(profitM),
      rate,
    };
  }

  function renderTabs() {
    if (!yearTabs) return;
    yearTabs.innerHTML = '';
    workbook.years.forEach((y, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fp-sheet-tab' + (i === active ? ' is-active' : '');
      btn.textContent = y.name || ('Año ' + (i + 1));
      btn.title = 'Clic para abrir · doble clic para renombrar';
      btn.addEventListener('click', () => {
        active = i;
        workbook.activeYear = i;
        renderAll();
      });
      btn.addEventListener('dblclick', (ev) => {
        ev.preventDefault();
        if (readOnly) return;
        const current = String(y.name || '');
        const next = window.prompt('Nombre del año', current);
        if (next == null) return;
        const trimmed = String(next).trim();
        if (!trimmed || trimmed === current) return;
        y.name = trimmed;
        markDirty();
        renderAll();
      });
      yearTabs.appendChild(btn);
    });
  }

  function nextYearLabel() {
    let max = null;
    (workbook.years || []).forEach((y) => {
      const n = parseInt(String(y?.name || ''), 10);
      if (Number.isFinite(n)) max = max == null ? n : Math.max(max, n);
    });
    let next = (max == null ? new Date().getFullYear() : max) + 1;
    const taken = new Set((workbook.years || []).map((y) => String(y?.name || '')));
    while (taken.has(String(next))) next += 1;
    return String(next);
  }

  function addYearSheet() {
    if (readOnly) return;
    const name = nextYearLabel();
    const prev = workbook.years[workbook.years.length - 1];
    const sheet = emptyYear(name);
    if (prev && Number.isFinite(Number(prev.savings_rate))) {
      sheet.savings_rate = Number(prev.savings_rate);
    }
    workbook.years.push(sheet);
    active = workbook.years.length - 1;
    workbook.activeYear = active;
    markDirty();
    setStatus('Año ' + name + ' agregado · Guardá para persistir', 'warn');
    renderAll();
  }

  function renderRate() {
    if (!rateInput) return;
    rateInput.value = String(Math.round((Number(year().savings_rate) || 0) * 1000) / 10);
  }

  function renderKpis() {
    if (!kpiEl) return;
    const s = statsOf(year());
    const ratePct = Math.round((s.rate || 0) * 1000) / 10;
    kpiEl.innerHTML = '';
    const cards = [
      ['Ingresos del año', moneyLabel(s.incomeAnnual), 'Ingresos cargados en este año', 'ok'],
      [
        'Balance anual',
        moneyLabel(s.balanceAnnual),
        'Ingresos − egresos',
        s.balanceAnnual >= 0 ? 'ok' : 'danger',
      ],
      [
        'Ahorro proyectado',
        moneyLabel(s.savingsAnnual),
        'Meta ' + ratePct + '% del balance',
        'slate',
      ],
      [
        'Disponible neto',
        moneyLabel(s.profitAnnual),
        'Balance − ahorro proyectado',
        s.profitAnnual >= 0 ? 'accent' : 'danger',
      ],
    ];
    cards.forEach(([label, value, hint, tone]) => {
      const div = document.createElement('div');
      div.className = 'pp-year-kpi' + (tone ? ' is-' + tone : '');
      div.innerHTML =
        '<span class="pp-year-kpi-label">' + label + '</span>' +
        '<strong class="pp-year-kpi-value">' + value + '</strong>' +
        '<span class="pp-year-kpi-hint">' + hint + '</span>';
      kpiEl.appendChild(div);
    });
    renderRealityPanel(s);
  }

  function renderRealityPanel(s) {
    const expenseHero = document.getElementById('pp-expense-hero');
    const expenseHint = document.getElementById('pp-expense-hero-hint');
    if (expenseHero) {
      expenseHero.textContent = moneyLabel(s.expenseAnnual);
    }
    if (expenseHint) {
      expenseHint.textContent = 'Promedio mensual ' + moneyLabel(s.expenseAnnual / 12);
    }

    const el = document.getElementById('pp-insight-dynamic');
    if (!el) return;
    const liq = Number(reality.liquidity) || 0;
    const liab = Number(reality.liabilities) || 0;
    const net = Number(reality.net_worth) || 0;
    const monthlyAvg = s.profitAnnual / 12;
    const cover = liab > 0.01 ? (liq / liab) : null;
    const projectedNet = net + s.profitAnnual;
    const netTone = projectedNet < 0 ? 'pp-tile-rose' : (projectedNet > 0 ? 'pp-tile-mint' : 'pp-tile-slate');
    const bits = [];
    bits.push(
      '<div class="pp-tile ' + netTone + '">' +
        '<span class="pp-tile-label">Neto si se cumple el año</span>' +
        '<strong class="pp-tile-value">' + moneyLabel(projectedNet) + '</strong>' +
        '<span class="pp-tile-hint">Neto actual + disponible neto proyectado</span>' +
      '</div>'
    );
    bits.push(
      '<div class="pp-tile pp-tile-slate">' +
        '<span class="pp-tile-label">Promedio mensual</span>' +
        '<strong class="pp-tile-value">' + moneyLabel(monthlyAvg) + '</strong>' +
        '<span class="pp-tile-hint">Disponible neto anual ÷ 12</span>' +
      '</div>'
    );
    bits.push(
      '<div class="pp-tile pp-tile-mint">' +
        '<span class="pp-tile-label">Gasto diario máximo</span>' +
        '<strong class="pp-tile-value">' + moneyLabel(monthlyAvg / 30) + '</strong>' +
        '<span class="pp-tile-hint">Resto del mes ÷ días · ref. ÷ 30</span>' +
      '</div>'
    );
    bits.push(
      '<div class="pp-tile pp-tile-slate">' +
        '<span class="pp-tile-label">Cobertura de pasivos</span>' +
        '<strong class="pp-tile-value">' + (cover != null ? (cover * 100).toFixed(0) + '%' : 'Sin pasivos') + '</strong>' +
        '<span class="pp-tile-hint">Liquidez ÷ pasivos reales</span>' +
      '</div>'
    );
    el.innerHTML = bits.join('');
  }

  function moneyInput(value) {
    const input = document.createElement('input');
    input.type = 'text';
    input.inputMode = 'decimal';
    input.className = 'fp-money';
    input.value = value ? formatMoneyDec(value) : '';
    input.placeholder = '—';
    if (readOnly) {
      input.readOnly = true;
      input.tabIndex = -1;
    }
    return input;
  }

  function renderLineTable(tbody, lines, kind) {
    if (!tbody) return;
    tbody.innerHTML = '';
    lines.forEach((line, idx) => {
      const tr = document.createElement('tr');
      const nameTd = document.createElement('td');
      const nameInput = document.createElement('input');
      nameInput.type = 'text';
      nameInput.className = 'fp-name';
      nameInput.value = line.name || '';
      nameInput.placeholder = kind === 'income' ? 'Fuente de ingreso' : 'Gasto / concepto';
      if (readOnly) {
        nameInput.readOnly = true;
        nameInput.tabIndex = -1;
      } else {
        nameInput.addEventListener('input', () => {
          line.name = nameInput.value;
          markDirty();
        });
      }
      nameTd.appendChild(nameInput);
      tr.appendChild(nameTd);

      for (let m = 0; m < 12; m++) {
        const td = document.createElement('td');
        td.className = 'num';
        const input = moneyInput(line.months[m] || 0);
        if (!readOnly) {
          input.addEventListener('change', () => {
            line.months[m] = parseNum(input.value);
            input.value = line.months[m] ? formatMoneyDec(line.months[m]) : '';
            markDirty();
            updateRowTotal(tr, line);
            renderKpis();
            renderSummary();
            renderCharts();
          });
        }
        td.appendChild(input);
        tr.appendChild(td);
      }

      const totalTd = document.createElement('td');
      totalTd.className = 'num fp-row-total';
      tr.appendChild(totalTd);
      updateRowTotal(tr, line);

      if (!readOnly) {
        const actTd = document.createElement('td');
        actTd.className = 'actions';
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'linkish';
        del.textContent = 'Quitar';
        del.addEventListener('click', () => {
          lines.splice(idx, 1);
          markDirty();
          renderAll();
        });
        actTd.appendChild(del);
        tr.appendChild(actTd);
      }
      tbody.appendChild(tr);
    });
  }

  function updateRowTotal(tr, line) {
    const td = tr.querySelector('.fp-row-total');
    if (!td) return;
    const sum = (line.months || []).reduce((a, b) => a + (Number(b) || 0), 0);
    td.textContent = sum ? formatMoneyDec(sum) : '—';
  }

  function renderSummary() {
    if (!summaryBody) return;
    const s = statsOf(year());
    const rows = [
      ['Ingresos', s.incomeM, s.incomeAnnual, 'ok'],
      ['Egresos', s.expenseM, s.expenseAnnual, 'danger'],
      ['Balance', s.balanceM, s.balanceAnnual, 'sep'],
      ['Ahorro', s.savingsM, s.savingsAnnual, ''],
      ['Disponible neto', s.profitM, s.profitAnnual, 'accent'],
    ];
    summaryBody.innerHTML = '';
    let posMonths = 0;
    let negMonths = 0;
    rows.forEach(([label, months, annual, tone]) => {
      const tr = document.createElement('tr');
      const tones = String(tone || '').split(/\s+/).filter(Boolean);
      const isNet = tones.includes('accent');
      tones.forEach((t) => tr.classList.add('fp-sum-' + t));
      const th = document.createElement('th');
      th.textContent = label;
      tr.appendChild(th);
      months.forEach((v) => {
        const n = Number(v) || 0;
        const td = document.createElement('td');
        td.className = 'num';
        td.textContent = n ? formatMoney(n) : '—';
        if (isNet) {
          if (n > 0.009) {
            td.classList.add('amount-in');
            posMonths += 1;
          } else if (n < -0.009) {
            td.classList.add('amount-out');
            negMonths += 1;
          }
        } else if (n < 0) {
          td.classList.add('amount-out');
        }
        tr.appendChild(td);
      });
      const tot = document.createElement('td');
      tot.className = 'num fp-row-total';
      tot.textContent = formatMoney(annual);
      if (isNet) {
        const a = Number(annual) || 0;
        if (a > 0.009) tot.classList.add('amount-in');
        else if (a < -0.009) tot.classList.add('amount-out');
      }
      tr.appendChild(tot);
      summaryBody.appendChild(tr);
    });
    const countEl = document.getElementById('pp-net-month-count');
    if (countEl) {
      const zeroMonths = Math.max(0, 12 - posMonths - negMonths);
      const pct = (n) => (n / 12) * 100;
      countEl.hidden = false;
      countEl.innerHTML =
        '<div class="fp-net-meter-head">' +
          '<span class="fp-net-meter-kicker">Disponible neto</span>' +
          '<span class="fp-net-meter-sub">Distribución de los 12 meses</span>' +
        '</div>' +
        '<div class="fp-net-meter-stats">' +
          '<div class="fp-net-meter-stat is-pos">' +
            '<strong class="fp-net-meter-num">' + posMonths + '</strong>' +
            '<span class="fp-net-meter-label">meses positivos</span>' +
          '</div>' +
          '<div class="fp-net-meter-stat is-neg">' +
            '<strong class="fp-net-meter-num">' + negMonths + '</strong>' +
            '<span class="fp-net-meter-label">meses negativos</span>' +
          '</div>' +
        '</div>' +
        '<div class="fp-net-meter-bar" role="presentation" aria-hidden="true">' +
          (posMonths ? '<span class="fp-net-meter-fill is-pos" style="width:' + pct(posMonths) + '%"></span>' : '') +
          (negMonths ? '<span class="fp-net-meter-fill is-neg" style="width:' + pct(negMonths) + '%"></span>' : '') +
          (zeroMonths ? '<span class="fp-net-meter-fill is-zero" style="width:' + pct(zeroMonths) + '%"></span>' : '') +
        '</div>';
    }
  }

  function chartTick(v) {
    try {
      return new Intl.NumberFormat('es-AR', { notation: 'compact' }).format(v);
    } catch (_) {
      return v;
    }
  }

  function destroyChart(key) {
    if (charts[key]) {
      charts[key].destroy();
      charts[key] = null;
    }
  }

  function planYearNum() {
    const n = parseInt(String(year().name || ''), 10);
    return Number.isFinite(n) && n >= 1900 ? n : new Date().getFullYear();
  }
  function daysInMonth(yearNum, monthIndex0) {
    return new Date(yearNum, monthIndex0 + 1, 0).getDate();
  }

  function lineAnnual(row) {
    return (row.months || []).reduce((a, b) => a + (Number(b) || 0), 0);
  }

  function renderCharts() {
    if (typeof Chart === 'undefined') return;
    const s = statsOf(year());
    const y = year();
    const grid = 'rgba(15, 23, 42, 0.08)';
    const tick = '#64748b';
    const axisOpts = {
      x: { ticks: { color: tick }, grid: { display: false } },
      y: {
        ticks: { color: tick, callback: chartTick },
        grid: { color: grid },
      },
    };

    if (flowChart) {
      destroyChart('flow');
      charts.flow = new Chart(flowChart, {
        type: 'bar',
        data: {
          labels: MONTHS,
          datasets: [
            { label: 'Ingresos', data: s.incomeM, backgroundColor: 'rgba(16, 185, 129, 0.75)', borderRadius: 4 },
            { label: 'Egresos', data: s.expenseM, backgroundColor: 'rgba(239, 68, 68, 0.65)', borderRadius: 4 },
            {
              label: 'Balance',
              data: s.balanceM,
              type: 'line',
              borderColor: '#047857',
              backgroundColor: 'transparent',
              tension: 0.25,
              pointRadius: 3,
              pointBorderColor: '#fff',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: '#475569', boxWidth: 12 } } },
          scales: axisOpts,
        },
      });
    }

    if (cumChartEl) {
      destroyChart('cum');
      let running = 0;
      const cum = s.balanceM.map((v) => {
        running += v;
        return running;
      });
      charts.cum = new Chart(cumChartEl, {
        type: 'line',
        data: {
          labels: MONTHS,
          datasets: [{
            label: 'Balance acumulado',
            data: cum,
            borderColor: '#0e7490',
            backgroundColor: 'rgba(14, 116, 144, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointBackgroundColor: '#0e7490',
            pointBorderColor: '#fff',
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: axisOpts,
        },
      });
    }

    if (breakChartEl) {
      destroyChart('break');
      const incomeRows = (y.income || [])
        .map((row) => ({ name: row.name || 'Ingreso', total: lineAnnual(row), kind: 'in' }))
        .filter((r) => Math.abs(r.total) > 0.009);
      const expenseRows = (y.expenses || [])
        .map((row) => ({ name: row.name || 'Gasto', total: lineAnnual(row), kind: 'out' }))
        .filter((r) => Math.abs(r.total) > 0.009);
      const rows = incomeRows.concat(expenseRows).sort((a, b) => Math.abs(b.total) - Math.abs(a.total)).slice(0, 12);
      const labels = rows.length ? rows.map((r) => r.name) : ['Sin datos'];
      const values = rows.length ? rows.map((r) => r.kind === 'out' ? -Math.abs(r.total) : Math.abs(r.total)) : [0];
      const colors = rows.length
        ? rows.map((r) => (r.kind === 'out' ? 'rgba(239, 68, 68, 0.7)' : 'rgba(16, 185, 129, 0.75)'))
        : ['rgba(148, 163, 184, 0.35)'];
      charts['break'] = new Chart(breakChartEl, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Total anual',
            data: values,
            backgroundColor: colors,
            borderRadius: 5,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => ' ' + moneyLabel(Math.abs(ctx.raw || 0)),
              },
            },
          },
          scales: {
            x: {
              ticks: { color: tick, callback: (v) => chartTick(Math.abs(v)) },
              grid: { color: grid },
            },
            y: {
              ticks: { color: tick, font: { size: 11 } },
              grid: { display: false },
            },
          },
        },
      });
    }

    const burdenMonths = s.incomeM.map((inc, i) => {
      const income = Number(inc) || 0;
      const expense = Math.max(0, Number(s.expenseM[i]) || 0);
      const savings = Math.max(0, Number(s.savingsM[i]) || 0);
      const profit = Number(s.profitM[i]) || 0;
      if (income <= 0.009) {
        return { expensePct: 0, savingsPct: 0, restPct: 0, income: 0, expense: 0, savings: 0, rest: 0 };
      }
      if (expense >= income - 0.009) {
        return {
          expensePct: 100,
          savingsPct: 0,
          restPct: 0,
          income,
          expense,
          savings: 0,
          rest: 0,
        };
      }
      const expensePct = Math.round((expense / income) * 1000) / 10;
      const savingsPct = Math.round((Math.min(savings, income - expense) / income) * 1000) / 10;
      const restAmt = Math.max(0, profit);
      let restPct = Math.round((restAmt / income) * 1000) / 10;
      const stacked = Math.round((expensePct + savingsPct + restPct) * 10) / 10;
      if (stacked !== 100) {
        restPct = Math.round((1000 - expensePct * 10 - savingsPct * 10)) / 10;
      }
      return {
        expensePct,
        savingsPct: Math.max(0, savingsPct),
        restPct: Math.max(0, restPct),
        income,
        expense,
        savings,
        rest: restAmt,
      };
    });
    const annualPct = s.incomeAnnual > 0.009
      ? Math.round((Math.max(0, s.expenseAnnual) / s.incomeAnnual) * 1000) / 10
      : null;
    if (burdenKpiValue) {
      burdenKpiValue.textContent = annualPct != null ? (annualPct.toLocaleString('es-AR') + '%') : '—';
    }

    const legendEl = document.getElementById('pp-burden-legend');
    if (legendEl) {
      const income = Math.max(0, s.incomeAnnual);
      const expense = Math.max(0, s.expenseAnnual);
      const savings = Math.max(0, s.savingsAnnual);
      const rest = Math.max(0, s.profitAnnual);
      const savingsPct = income > 0.009 ? Math.round((savings / income) * 1000) / 10 : null;
      const restPct = income > 0.009 ? Math.round((rest / income) * 1000) / 10 : null;
      const categoryRows = (y.expenses || [])
        .map((row, idx) => {
          const amount = Math.max(0, lineAnnual(row));
          const name = String(row.name || '').trim() || ('Egreso ' + (idx + 1));
          const pct = income > 0.009 ? Math.round((amount / income) * 1000) / 10 : 0;
          return { name, amount, pct };
        })
        .filter((c) => c.amount > 0.009)
        .sort((a, b) => b.pct - a.pct || b.amount - a.amount);
      let html = '';
      if (income > 0.009) {
        html += '<li><span class="burden-swatch is-expense"></span><div><strong>Egresos totales</strong><span>'
          + moneyLabel(expense) + ' · ' + (annualPct ?? 0).toLocaleString('es-AR') + '%</span></div></li>';
        categoryRows.forEach((c) => {
          html += '<li><span class="burden-swatch is-out"></span><div><strong>'
            + escapeHtml(c.name) + '</strong><span>'
            + moneyLabel(c.amount) + ' · ' + c.pct.toLocaleString('es-AR') + '% del ingreso</span></div></li>';
        });
        html += '<li><span class="burden-swatch is-savings"></span><div><strong>Ahorro proyectado</strong><span>'
          + moneyLabel(savings) + (savingsPct != null ? ' · ' + savingsPct.toLocaleString('es-AR') + '%' : '')
          + '</span></div></li>';
        html += '<li><span class="burden-swatch is-rest"></span><div><strong>Disponible neto</strong><span>'
          + moneyLabel(rest) + (restPct != null ? ' · ' + restPct.toLocaleString('es-AR') + '%' : '')
          + '</span></div></li>';
      } else {
        html = '<li><div><strong>Sin ingresos</strong><span>Cargá ingresos en la planilla</span></div></li>';
      }
      legendEl.innerHTML = html;
    }

    if (burdenMonthsEl) {
      destroyChart('burdenMonths');
      const expenseColors = burdenMonths.map((m) => (m.expensePct >= 50 ? '#ef4444' : '#94a3b8'));
      charts.burdenMonths = new Chart(burdenMonthsEl, {
        type: 'bar',
        data: {
          labels: MONTHS,
          datasets: [
            {
              label: 'Egresos',
              data: burdenMonths.map((m) => m.expensePct),
              backgroundColor: expenseColors,
              borderRadius: 4,
              stack: 'burden',
              barPercentage: 0.78,
              categoryPercentage: 0.82,
            },
            {
              label: 'Ahorro',
              data: burdenMonths.map((m) => m.savingsPct),
              backgroundColor: '#0ea5e9',
              borderRadius: 4,
              stack: 'burden',
              barPercentage: 0.78,
              categoryPercentage: 0.82,
            },
            {
              label: 'Disponible neto',
              data: burdenMonths.map((m) => m.restPct),
              backgroundColor: '#10b981',
              borderRadius: 4,
              stack: 'burden',
              barPercentage: 0.78,
              categoryPercentage: 0.82,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const i = ctx.dataIndex;
                  const m = burdenMonths[i];
                  if (!m || m.income <= 0.009) return ' Sin ingresos este mes';
                  const idx = ctx.datasetIndex;
                  const pct = idx === 0 ? m.expensePct : (idx === 1 ? m.savingsPct : m.restPct);
                  const amt = idx === 0 ? m.expense : (idx === 1 ? m.savings : m.rest);
                  return ' ' + ctx.dataset.label + ': ' + moneyLabel(amt) + ' (' + pct.toLocaleString('es-AR') + '%)';
                },
              },
            },
          },
          scales: {
            x: { stacked: true, ticks: { color: tick }, grid: { display: false } },
            y: {
              stacked: true,
              beginAtZero: true,
              max: 100,
              ticks: { color: tick, callback: (v) => v + '%' },
              grid: { color: grid },
            },
          },
        },
      });
    }

    if (burdenTotalEl) {
      destroyChart('burdenTotal');
      const income = Math.max(0, s.incomeAnnual);
      const expense = Math.max(0, s.expenseAnnual);
      const savings = Math.max(0, s.savingsAnnual);
      const rest = Math.max(0, s.profitAnnual);
      charts.burdenTotal = new Chart(burdenTotalEl, {
        type: 'doughnut',
        data: {
          labels: income > 0.009 ? ['Egresos', 'Ahorro', 'Disponible neto'] : ['Sin ingresos'],
          datasets: [{
            data: income > 0.009 ? [expense, savings, rest] : [1],
            backgroundColor: income > 0.009
              ? ['#334155', '#0ea5e9', '#10b981']
              : ['rgba(148, 163, 184, 0.35)'],
            hoverBackgroundColor: income > 0.009
              ? ['#1e293b', '#0284c7', '#059669']
              : ['rgba(148, 163, 184, 0.45)'],
            borderWidth: 0,
            spacing: income > 0.009 ? 2 : 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: (items) => (items[0] && items[0].label) || '',
                label: (ctx) => {
                  if (income <= 0.009) return ' Sin ingresos cargados';
                  const v = Number(ctx.raw) || 0;
                  const pct = Math.round((v / income) * 1000) / 10;
                  return ' ' + moneyLabel(v) + ' · ' + pct.toLocaleString('es-AR') + '%';
                },
              },
            },
          },
        },
      });
    }

    if (burdenCategoriesEl) {
      destroyChart('burdenCategories');
      const income = Math.max(0, s.incomeAnnual);
      const catPalette = ['#ef4444', '#f97316', '#eab308', '#84cc16', '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b', '#0f766e'];
      const categories = (y.expenses || [])
        .map((row, idx) => {
          const amount = Math.max(0, lineAnnual(row));
          const name = String(row.name || '').trim() || ('Egreso ' + (idx + 1));
          const pct = income > 0.009 ? Math.round((amount / income) * 1000) / 10 : 0;
          return { name, amount, pct };
        })
        .filter((c) => c.amount > 0.009)
        .sort((a, b) => b.pct - a.pct || b.amount - a.amount);

      const wrap = burdenCategoriesEl.parentElement;
      if (wrap) {
        wrap.style.height = Math.max(220, 56 + Math.max(categories.length, 1) * 38) + 'px';
      }

      charts.burdenCategories = new Chart(burdenCategoriesEl, {
        type: 'bar',
        data: {
          labels: categories.length ? categories.map((c) => c.name) : ['Sin egresos'],
          datasets: [{
            label: '% del ingreso',
            data: categories.length ? categories.map((c) => c.pct) : [0],
            backgroundColor: categories.length
              ? categories.map((_, i) => catPalette[i % catPalette.length])
              : ['#cbd5e1'],
            borderRadius: 5,
            barPercentage: 0.7,
            categoryPercentage: 0.78,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const i = ctx.dataIndex;
                  const c = categories[i];
                  if (!c) return ' Sin egresos cargados';
                  return ' ' + moneyLabel(c.amount) + ' · ' + c.pct.toLocaleString('es-AR') + '% del ingreso';
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              suggestedMax: categories.length
                ? Math.min(100, Math.max(10, Math.ceil((categories[0].pct * 1.2) / 5) * 5))
                : 100,
              ticks: { color: tick, callback: (v) => v + '%' },
              grid: { color: grid },
            },
            y: {
              ticks: { color: tick },
              grid: { display: false },
            },
          },
        },
      });
    }

    const monthlyAvg = s.profitAnnual / 12;
    const dailyRef = monthlyAvg / 30;
    const yNum = planYearNum();
    const daysPerMonth = MONTHS.map((_, i) => daysInMonth(yNum, i));
    const dailyByMonth = daysPerMonth.map((d, i) => {
      // Disponible neto del mes = balance − ahorro (ya en profitM).
      const resto = Number(s.profitM[i]) || 0;
      return d > 0 ? Math.round((resto / d) * 100) / 100 : 0;
    });
    if (dailyKpiValue) {
      dailyKpiValue.textContent = moneyLabel(dailyRef);
    }
    if (dailyMonthCards) {
      dailyMonthCards.innerHTML = MONTHS.map((label, i) => {
        const days = daysPerMonth[i];
        const val = dailyByMonth[i];
        const neg = val < 0 ? ' is-neg' : '';
        return '<div class="pp-daily-month-card' + neg + '">'
          + '<span class="pp-daily-month-name">' + label + '</span>'
          + '<strong class="pp-daily-month-value">' + moneyLabel(val) + '</strong>'
          + '<span class="pp-daily-month-hint">' + days + ' días</span>'
          + '</div>';
      }).join('');
    }

    if (dailyMonthsEl) {
      destroyChart('dailyMonths');
      const avgLine = MONTHS.map(() => Math.round(dailyRef * 100) / 100);
      charts.dailyMonths = new Chart(dailyMonthsEl, {
        type: 'bar',
        data: {
          labels: MONTHS,
          datasets: [
            {
              label: 'Disponible diario del mes',
              data: dailyByMonth,
              backgroundColor: dailyByMonth.map((v) => (v < 0 ? 'rgba(239, 68, 68, 0.7)' : 'rgba(16, 185, 129, 0.75)')),
              borderRadius: 4,
              barPercentage: 0.75,
              categoryPercentage: 0.82,
              order: 2,
            },
            {
              label: 'Referencia ÷ 30',
              data: avgLine,
              type: 'line',
              borderColor: '#047857',
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [6, 4],
              pointRadius: 0,
              tension: 0,
              order: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: '#475569', boxWidth: 10, font: { size: 11 } },
            },
            tooltip: {
              callbacks: {
                afterTitle: (items) => {
                  const i = items[0] && items[0].dataIndex;
                  return i != null ? daysPerMonth[i] + ' días' : '';
                },
                label: (ctx) => ' ' + ctx.dataset.label + ': ' + moneyLabel(ctx.raw || 0),
              },
            },
          },
          scales: {
            x: { ticks: { color: tick }, grid: { display: false } },
            y: {
              ticks: { color: tick, callback: chartTick },
              grid: { color: grid },
            },
          },
        },
      });
    }
  }

  function renderTables() {
    const y = year();
    if (!Array.isArray(y.income)) y.income = [];
    if (!Array.isArray(y.expenses)) y.expenses = [];
    renderLineTable(incomeBody, y.income, 'income');
    renderLineTable(expensesBody, y.expenses, 'expenses');
  }

  function renderAll() {
    renderTabs();
    renderRate();
    renderTables();
    renderKpis();
    renderSummary();
    renderCharts();
  }

  async function save() {
    if (readOnly) return;
    workbook.activeYear = active;
    workbook.version = 2;
    workbook.template = 'personal';
    setStatus('Guardando…');
    try {
      const res = await fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({ _csrf: csrf, workbook }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar');
      workbook = data.workbook || workbook;
      active = clamp(workbook.activeYear || 0, 0, workbook.years.length - 1);
      dirty = false;
      setStatus('Guardado' + (data.updated_at ? ' · ' + data.updated_at : ''), 'ok');
      renderAll();
    } catch (err) {
      setStatus(err.message || 'Error al guardar', 'err');
    }
  }

  if (!readOnly && rateInput) {
    rateInput.addEventListener('change', () => {
      let pct = Number(String(rateInput.value).replace(',', '.'));
      if (!Number.isFinite(pct)) pct = 10;
      pct = clamp(pct, 0, 100);
      year().savings_rate = pct / 100;
      rateInput.value = String(pct);
      markDirty();
      renderKpis();
      renderSummary();
      renderCharts();
    });
  }
  if (!readOnly && btnAddIncome) {
    btnAddIncome.addEventListener('click', () => {
      year().income.push(emptyLine(''));
      markDirty();
      renderAll();
    });
  }
  if (!readOnly && btnAddExpense) {
    btnAddExpense.addEventListener('click', () => {
      year().expenses.push(emptyLine(''));
      markDirty();
      renderAll();
    });
  }
  if (!readOnly && btnAddYear) {
    btnAddYear.addEventListener('click', (ev) => {
      ev.preventDefault();
      addYearSheet();
    });
  }
  if (!readOnly && btnSave) btnSave.addEventListener('click', save);
  if (!readOnly) {
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
      }
    });
    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  const boot = () => renderAll();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(boot, 30));
  } else {
    setTimeout(boot, 30);
  }
})();
