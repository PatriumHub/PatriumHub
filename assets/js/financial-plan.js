/**
 * Estados y proyección — dashboard + tablas HTML editables (v2).
 */
(function () {
  'use strict';

  const root = document.getElementById('fp-root');
  if (!root) return;

  const readOnly = root.dataset.readonly === '1';
  const payloadEl = document.getElementById('fp-workbook');
  const saveUrl = root.dataset.saveUrl || '';
  const csrf = root.dataset.csrf || '';
  const statusEl = document.getElementById('fp-status');
  const yearTabs = document.getElementById('fp-year-tabs');
  const kpiEl = document.getElementById('fp-kpis');
  const chartCanvas = document.getElementById('fp-compare-chart');
  const chartTabs = document.getElementById('fp-chart-tabs');
  const pieYearWrap = document.getElementById('fp-pie-year-wrap');
  const pieYearSelect = document.getElementById('fp-pie-year');
  const chartHint = document.getElementById('fp-chart-hint');
  const yearsChartCanvas = document.getElementById('fp-years-chart');
  const yearsChartTabs = document.getElementById('fp-years-chart-tabs');
  const allYearsKpis = document.getElementById('fp-all-years-kpis');
  const foldCompare = document.getElementById('fp-fold-compare');
  const clientsBody = document.getElementById('fp-clients-body');
  const productsIncomeBody = document.getElementById('fp-products-income-body');
  const expensesBody = document.getElementById('fp-expenses-body');
  const summaryBody = document.getElementById('fp-summary-body');
  const rateInput = document.getElementById('fp-savings-rate');
  const splitInput = document.getElementById('fp-profit-split-n');
  const btnSave = document.getElementById('fp-save');
  const btnAddClient = document.getElementById('fp-add-client');
  const btnAddExpense = document.getElementById('fp-add-expense');
  const btnAddYear = document.getElementById('fp-add-year');
  const clientsSection = document.getElementById('fp-clients-section');
  const productsSection = document.getElementById('fp-products-income-section');
  const burdenMonthsEl = document.getElementById('fp-burden-months');
  const burdenTotalEl = document.getElementById('fp-burden-total');
  const burdenCategoriesEl = document.getElementById('fp-burden-categories');
  const burdenKpiValue = document.getElementById('fp-burden-kpi-value');
  const dailyMonthsEl = document.getElementById('fp-daily-months');
  const dailyKpiValue = document.getElementById('fp-daily-kpi-value');
  const dailyMonthCards = document.getElementById('fp-daily-month-cards');
  const template = (root.dataset.template || 'services') === 'products' ? 'products' : 'services';
  const isProducts = template === 'products';

  const MONTHS = [
    'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
    'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic',
  ];

  let workbook;
  try {
    workbook = JSON.parse(payloadEl?.textContent || '{}');
  } catch (_) {
    workbook = { version: 2, activeYear: 0, years: [] };
  }
  if (!Array.isArray(workbook.years) || !workbook.years.length) {
    const y = new Date().getFullYear();
    workbook = {
      version: 2,
      template,
      activeYear: 1,
      profit_split_n: 1,
      years: [emptyYear(String(y - 1)), emptyYear(String(y)), emptyYear(String(y + 1))],
    };
  }
  workbook.template = template;
  if (!workbook.profit_split_n) workbook.profit_split_n = 1;

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

  function emptyMonths() {
    return Array(12).fill(0);
  }

  function emptyLine() {
    return { name: '', months: emptyMonths() };
  }

  function emptyYear(name) {
    if (isProducts) {
      return {
        name,
        savings_rate: 0.1,
        income_months: emptyMonths(),
        clients: [],
        expenses: Array.from({ length: 4 }, emptyLine),
      };
    }
    return {
      name,
      savings_rate: 0.1,
      clients: Array.from({ length: 4 }, emptyLine),
      expenses: Array.from({ length: 4 }, emptyLine),
    };
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function year() {
    return workbook.years[active];
  }

  function parseNum(raw) {
    if (raw === null || raw === undefined || raw === '') return 0;
    let s = String(raw).trim();
    if (s.includes(',')) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else if ((s.match(/\./g) || []).length > 1) {
      s = s.replace(/\./g, '');
    }
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  function formatMoney(n) {
    return new Intl.NumberFormat('es-AR', {
      maximumFractionDigits: 0,
      minimumFractionDigits: 0,
    }).format(Math.round(n || 0));
  }

  function formatMoneyDec(n) {
    return new Intl.NumberFormat('es-AR', {
      maximumFractionDigits: 2,
      minimumFractionDigits: 0,
    }).format(n || 0);
  }

  function setStatus(msg, kind) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'fp-status' + (kind ? ' is-' + kind : '');
  }

  function markDirty() {
    dirty = true;
    setStatus('Cambios sin guardar', 'warn');
  }

  function lineActive(line) {
    const name = (line.name || '').trim();
    const sum = (line.months || []).reduce((a, b) => a + (Number(b) || 0), 0);
    return name !== '' || sum > 0;
  }

  function statsForYear(y) {
    const rate = Number(y.savings_rate) || 0;
    const incomeM = emptyMonths();
    const expenseM = emptyMonths();
    if (isProducts) {
      const src = y.income_months || emptyMonths();
      for (let i = 0; i < 12; i++) incomeM[i] = Number(src[i]) || 0;
    } else {
      (y.clients || []).forEach((line) => {
        for (let i = 0; i < 12; i++) incomeM[i] += Number(line.months[i]) || 0;
      });
    }
    (y.expenses || []).forEach((line) => {
      for (let i = 0; i < 12; i++) expenseM[i] += Number(line.months[i]) || 0;
    });
    const balanceM = incomeM.map((v, i) => v - expenseM[i]);
    const savingsM = balanceM.map((v) => (v > 0 ? v * rate : 0));
    const profitM = balanceM.map((v, i) => v - savingsM[i]);
    const monthsWithIncome = incomeM.filter((v) => v > 0).length;
    return {
      name: y.name || '',
      incomeM,
      expenseM,
      balanceM,
      savingsM,
      profitM,
      incomeAnnual: incomeM.reduce((a, b) => a + b, 0),
      expenseAnnual: expenseM.reduce((a, b) => a + b, 0),
      balanceAnnual: balanceM.reduce((a, b) => a + b, 0),
      savingsAnnual: savingsM.reduce((a, b) => a + b, 0),
      profitAnnual: profitM.reduce((a, b) => a + b, 0),
      clientsCount: (y.clients || []).filter(lineActive).length,
      expensesCount: (y.expenses || []).filter(lineActive).length,
      monthsWithIncome,
      rate,
    };
  }

  function stats() {
    return statsForYear(year());
  }

  function setActiveYear(i) {
    const max = Math.max(0, workbook.years.length - 1);
    active = clamp(Number(i) || 0, 0, max);
    workbook.activeYear = active;
    comparePieYear = active;
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
        setActiveYear(i);
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
    setActiveYear(workbook.years.length - 1);
    markDirty();
    setStatus('Año ' + name + ' agregado · Guardá para persistir', 'warn');
    renderAll();
  }

  function splitN() {
    let n = Number(workbook.profit_split_n || 1);
    if (!Number.isFinite(n) || n < 1) n = 1;
    if (n > 99) n = 99;
    return Math.floor(n);
  }

  function setSplitN(n) {
    workbook.profit_split_n = clamp(Math.floor(Number(n) || 1), 1, 99);
  }

  function syncSplitInput() {
    if (splitInput) splitInput.value = String(splitN());
  }

  function kpiCard(label, value, hint, tone) {
    const div = document.createElement('div');
    div.className = 'fp-kpi' + (tone ? ' is-' + tone : '');
    div.innerHTML =
      '<span class="fp-kpi-label"></span><div class="fp-kpi-value"></div><span class="fp-kpi-hint"></span>';
    div.querySelector('.fp-kpi-label').textContent = label;
    div.querySelector('.fp-kpi-value').textContent = value;
    div.querySelector('.fp-kpi-hint').textContent = hint || '';
    return div;
  }

  function renderKpis() {
    if (!kpiEl) return;
    const s = stats();
    kpiEl.innerHTML = '';
    const incomeHint = isProducts
      ? s.monthsWithIncome + ' meses con ingreso'
      : s.clientsCount + ' clientes';
    kpiEl.appendChild(kpiCard('Ingresos anuales', '$' + formatMoney(s.incomeAnnual), incomeHint, 'ok'));
    kpiEl.appendChild(kpiCard('Egresos anuales', '$' + formatMoney(s.expenseAnnual), s.expensesCount + ' ítems', 'danger'));
    kpiEl.appendChild(kpiCard('Balance anual', '$' + formatMoney(s.balanceAnnual), 'Ingresos − egresos', s.balanceAnnual >= 0 ? 'ok' : 'danger'));
    kpiEl.appendChild(kpiCard('Ahorro anual', '$' + formatMoney(s.savingsAnnual), (s.rate * 100).toFixed(0) + '% del balance'));
    kpiEl.appendChild(kpiCard('Ganancia neta', '$' + formatMoney(s.profitAnnual), 'Balance − ahorro', 'accent'));
    if (isProducts) {
      kpiEl.appendChild(kpiCard('Meses activos', String(s.monthsWithIncome), 'Con ingreso > 0'));
    } else {
      kpiEl.appendChild(kpiCard('Clientes', String(s.clientsCount), 'Con nombre o monto'));
    }
  }

  function moneyTick(v) {
    try {
      return new Intl.NumberFormat('es-AR', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
    } catch (_) {
      return String(v);
    }
  }

  const MONTHLY_SERIES = [
    { key: 'incomeM', label: 'Ingresos', color: '#10b981' },
    { key: 'expenseM', label: 'Egresos', color: '#ef4444' },
    { key: 'balanceM', label: 'Balance', color: '#3b82f6' },
    { key: 'savingsM', label: 'Ahorro', color: '#06b6d4' },
    { key: 'profitM', label: 'Ganancia neta', color: '#047857' },
  ];

  let compareChart = null;
  let yearsChart = null;
  let compareChartType = 'line';
  let yearsChartType = 'bar';
  let comparePieYear = active;
  let chartWaitTimer = null;
  const chartReadyQueue = [];
  const charts = {
    burdenMonths: null,
    burdenTotal: null,
    burdenCategories: null,
    dailyMonths: null,
  };

  function destroyCompareChart() {
    if (compareChart) {
      compareChart.destroy();
      compareChart = null;
    }
  }

  function destroyYearsChart() {
    if (yearsChart) {
      yearsChart.destroy();
      yearsChart = null;
    }
  }

  function destroyChart(key) {
    if (charts[key]) {
      charts[key].destroy();
      charts[key] = null;
    }
  }

  function moneyLabel(v) {
    return '$' + formatMoney(v);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function chartTick(v) {
    return moneyTick(v);
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

  function whenChartReady(cb) {
    if (typeof window.Chart === 'function') {
      cb();
      return;
    }
    chartReadyQueue.push(cb);
    if (chartWaitTimer) return;
    let n = 0;
    chartWaitTimer = setInterval(() => {
      n += 1;
      if (typeof window.Chart === 'function') {
        clearInterval(chartWaitTimer);
        chartWaitTimer = null;
        const queue = chartReadyQueue.splice(0, chartReadyQueue.length);
        queue.forEach((fn) => {
          try { fn(); } catch (err) { console.error(err); }
        });
      } else if (n > 80) {
        clearInterval(chartWaitTimer);
        chartWaitTimer = null;
        chartReadyQueue.length = 0;
        if (chartHint) chartHint.textContent = 'No se pudo cargar Chart.js.';
      }
    }, 50);
  }

  function syncPieYearSelect(years) {
    if (!pieYearSelect) return;
    pieYearSelect.innerHTML = '';
    years.forEach((y, i) => {
      const opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = y.name || String(i + 1);
      pieYearSelect.appendChild(opt);
    });
    const idx = clamp(active, 0, Math.max(0, years.length - 1));
    comparePieYear = idx;
    pieYearSelect.value = String(idx);
  }

  function buildLineBarConfig(yearStats, type) {
    const grid = 'rgba(15, 23, 42, 0.08)';
    const tick = '#64748b';
    const isBar = type === 'bar';
    return {
      type,
      data: {
        labels: MONTHS.slice(),
        datasets: MONTHLY_SERIES.map((s) => ({
          label: s.label,
          data: (yearStats[s.key] || emptyMonths()).map((v) => Number(v) || 0),
          borderColor: s.color,
          backgroundColor: isBar ? s.color + 'bb' : s.color,
          borderWidth: isBar ? 0 : 2.5,
          tension: 0.3,
          pointRadius: isBar ? 0 : 5,
          pointHoverRadius: isBar ? 0 : 7,
          pointBackgroundColor: s.color,
          pointBorderColor: '#ffffff',
          pointBorderWidth: 1.5,
          fill: false,
          maxBarThickness: 22,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        datasets: isBar
          ? {
              bar: {
                categoryPercentage: 0.55,
                barPercentage: 0.5,
              },
            }
          : undefined,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#475569',
              boxWidth: 10,
              padding: 14,
              font: { size: 12, weight: '500' },
              usePointStyle: true,
              pointStyle: 'circle',
            },
          },
          tooltip: {
            callbacks: {
              title(items) {
                const m = items[0]?.label || '';
                return m + ' · ' + (yearStats.name || '');
              },
              label(ctx) {
                const v = ctx.parsed.y;
                return ' ' + ctx.dataset.label + ': $' + formatMoney(v);
              },
            },
          },
          title: {
            display: true,
            text: (yearStats.name || 'Año') + ' · por mes',
            color: '#475569',
            font: { size: 13, weight: '500' },
            padding: { bottom: 8 },
          },
        },
        scales: {
          x: {
            ticks: { color: tick, maxRotation: 0, autoSkip: false },
            grid: { display: false },
            offset: isBar,
          },
          y: {
            ticks: { color: tick, callback: moneyTick },
            grid: { color: grid },
          },
        },
      },
    };
  }

  function buildPieConfig(yearStats) {
    const slices = [
      { label: 'Egresos', value: Math.max(0, Number(yearStats.expenseAnnual) || 0), color: '#ef4444' },
      { label: 'Ahorro', value: Math.max(0, Number(yearStats.savingsAnnual) || 0), color: '#06b6d4' },
      { label: 'Ganancia neta', value: Math.max(0, Number(yearStats.profitAnnual) || 0), color: '#047857' },
    ];
    const total = slices.reduce((a, s) => a + s.value, 0);
    return {
      type: 'doughnut',
      data: {
        labels: slices.map((s) => s.label),
        datasets: [{
          data: slices.map((s) => s.value),
          backgroundColor: slices.map((s) => s.color),
          borderWidth: 0,
          hoverOffset: 8,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#475569',
              boxWidth: 10,
              padding: 14,
              font: { size: 12, weight: '500' },
              usePointStyle: true,
              pointStyle: 'circle',
            },
          },
          tooltip: {
            callbacks: {
              label(ctx) {
                const v = ctx.parsed || 0;
                const pct = total > 0 ? ((v / total) * 100).toFixed(1) : '0';
                return ' ' + ctx.label + ': $' + formatMoney(v) + ' (' + pct + '%)';
              },
            },
          },
          title: {
            display: true,
            text: (yearStats.name || 'Año') + ' · composición del ingreso',
            color: '#475569',
            font: { size: 13, weight: '500' },
            padding: { bottom: 8 },
          },
        },
      },
    };
  }

  function buildYearsCompareConfig(years, type) {
    const grid = 'rgba(15, 23, 42, 0.08)';
    const tick = '#64748b';
    const isBar = type === 'bar';
    const series = [
      { key: 'profitAnnual', label: 'Ganancia neta', color: '#047857' },
      { key: 'expenseAnnual', label: 'Egresos', color: '#ef4444' },
      { key: 'savingsAnnual', label: 'Ahorro', color: '#06b6d4' },
    ];
    return {
      type,
      data: {
        labels: years.map((y) => y.name || '—'),
        datasets: series.map((s) => ({
          label: s.label,
          data: years.map((y) => Number(y[s.key]) || 0),
          borderColor: s.color,
          backgroundColor: isBar ? s.color + 'bb' : s.color,
          borderWidth: isBar ? 0 : 2.5,
          tension: 0.3,
          pointRadius: isBar ? 0 : 5,
          pointHoverRadius: isBar ? 0 : 7,
          pointBackgroundColor: s.color,
          pointBorderColor: '#ffffff',
          pointBorderWidth: 1.5,
          fill: false,
          maxBarThickness: 48,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        datasets: isBar
          ? {
              bar: {
                categoryPercentage: 0.5,
                barPercentage: 0.55,
              },
            }
          : undefined,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#475569',
              boxWidth: 10,
              padding: 14,
              font: { size: 12, weight: '500' },
              usePointStyle: true,
              pointStyle: 'circle',
            },
          },
          tooltip: {
            callbacks: {
              label(ctx) {
                return ' ' + ctx.dataset.label + ': $' + formatMoney(ctx.parsed.y);
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: tick },
            grid: { display: false },
            offset: isBar,
          },
          y: {
            ticks: { color: tick, callback: moneyTick },
            grid: { color: grid },
          },
        },
      },
    };
  }

  function renderYearsCompare(years) {
    if (!yearsChartCanvas) return;
    if (!years.length) {
      destroyYearsChart();
      return;
    }
    whenChartReady(() => {
      destroyYearsChart();
      yearsChart = new window.Chart(
        yearsChartCanvas,
        buildYearsCompareConfig(years, yearsChartType === 'line' ? 'line' : 'bar')
      );
    });
  }

  function renderAllYearsKpis(years) {
    if (!allYearsKpis) return;
    allYearsKpis.innerHTML = '';
    const n = years.length;
    if (!n) return;

    const totals = years.reduce(
      (acc, y) => {
        acc.income += Number(y.incomeAnnual) || 0;
        acc.expense += Number(y.expenseAnnual) || 0;
        acc.savings += Number(y.savingsAnnual) || 0;
        acc.profit += Number(y.profitAnnual) || 0;
        return acc;
      },
      { income: 0, expense: 0, savings: 0, profit: 0 }
    );

    const hint = n === 1 ? '1 año cargado' : 'Suma de ' + n + ' años';
    const cards = [
      { label: 'Ingresos totales', value: totals.income, tone: 'ok', hint },
      { label: 'Egresos totales', value: totals.expense, tone: 'danger', hint },
      { label: 'Ahorro total', value: totals.savings, tone: 'savings', hint },
      { label: 'Ganancia neta total', value: totals.profit, tone: 'accent', hint },
    ];

    cards.forEach((c) => {
      const div = document.createElement('div');
      div.className = 'fp-all-kpi' + (c.tone ? ' is-' + c.tone : '');
      div.innerHTML =
        '<span class="fp-all-kpi-label"></span>' +
        '<div class="fp-all-kpi-value"></div>' +
        '<span class="fp-all-kpi-hint"></span>';
      div.querySelector('.fp-all-kpi-label').textContent = c.label;
      div.querySelector('.fp-all-kpi-value').textContent = '$' + formatMoney(c.value);
      div.querySelector('.fp-all-kpi-hint').textContent = c.hint;
      allYearsKpis.appendChild(div);
    });
  }

  function renderYearCompare() {
    const years = (workbook.years || []).map(statsForYear);
    renderAllYearsKpis(years);

    if (!chartCanvas) return;
    if (!years.length) {
      destroyCompareChart();
      destroyYearsChart();
      if (chartHint) chartHint.textContent = 'Sin años para graficar.';
      return;
    }

    syncPieYearSelect(years);
    if (pieYearWrap) pieYearWrap.hidden = false;
    const idx = clamp(comparePieYear, 0, years.length - 1);
    const yearStats = years[idx];
    if (chartHint) {
      chartHint.textContent = compareChartType === 'doughnut'
        ? 'Torta anual: egresos + ahorro + ganancia (= ingresos). Clickeá la leyenda para ocultar segmentos.'
        : 'Eje X = meses. Cada punto es un mes. Cambiá el año arriba; en la leyenda podés ocultar series.';
    }

    whenChartReady(() => {
      destroyCompareChart();
      const cfg = compareChartType === 'doughnut'
        ? buildPieConfig(yearStats)
        : buildLineBarConfig(yearStats, compareChartType === 'bar' ? 'bar' : 'line');
      compareChart = new window.Chart(chartCanvas, cfg);
    });
    renderYearsCompare(years);
  }

  function setCompareChartType(type) {
    if (!['line', 'bar', 'doughnut'].includes(type)) return;
    compareChartType = type;
    if (chartTabs) {
      chartTabs.querySelectorAll('[data-chart]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.getAttribute('data-chart') === type);
      });
    }
    renderYearCompare();
  }

  function setYearsChartType(type) {
    if (!['line', 'bar'].includes(type)) return;
    yearsChartType = type;
    if (yearsChartTabs) {
      yearsChartTabs.querySelectorAll('[data-years-chart]').forEach((btn) => {
        btn.classList.toggle('is-active', btn.getAttribute('data-years-chart') === type);
      });
    }
    const years = (workbook.years || []).map(statsForYear);
    renderYearsCompare(years);
  }

  if (chartTabs) {
    chartTabs.addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-chart]');
      if (!btn) return;
      setCompareChartType(btn.getAttribute('data-chart'));
    });
  }
  if (yearsChartTabs) {
    yearsChartTabs.addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-years-chart]');
      if (!btn) return;
      setYearsChartType(btn.getAttribute('data-years-chart'));
    });
  }
  if (pieYearSelect) {
    pieYearSelect.addEventListener('change', () => {
      setActiveYear(Number(pieYearSelect.value) || 0);
      renderAll();
    });
  }
  if (foldCompare) {
    foldCompare.addEventListener('toggle', () => {
      if (foldCompare.open) {
        requestAnimationFrame(() => {
          if (compareChart) compareChart.resize();
          if (yearsChart) yearsChart.resize();
        });
      }
    });
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
      nameInput.placeholder = kind === 'clients' ? 'Cliente' : 'Costo / proveedor';
      if (readOnly) {
        nameInput.readOnly = true;
        nameInput.tabIndex = -1;
      } else {
        nameInput.addEventListener('input', () => {
          line.name = nameInput.value;
          markDirty();
          renderKpis();
          renderSummary();
          renderBurdenAndDaily();
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
            renderYearCompare();
            renderKpis();
            renderSummary();
            renderBurdenAndDaily();
            updateRowTotal(tr, line);
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

    if (!lines.length) {
      const tr = document.createElement('tr');
      tr.innerHTML = readOnly
        ? '<td colspan="14" class="muted">Sin filas.</td>'
        : '<td colspan="15" class="muted">Sin filas. Agregá una para empezar.</td>';
      tbody.appendChild(tr);
    }
  }

  function updateRowTotal(tr, line) {
    const td = tr.querySelector('.fp-row-total');
    if (!td) return;
    const sum = (line.months || []).reduce((a, b) => a + (Number(b) || 0), 0);
    td.textContent = sum ? formatMoneyDec(sum) : '—';
  }

  function renderSummary() {
    if (!summaryBody) return;
    const s = stats();
    const n = splitN();
    const rows = [
      ['Ingresos', s.incomeM, s.incomeAnnual, 'ok'],
      ['Egresos', s.expenseM, s.expenseAnnual, 'danger'],
      ['Balance', s.balanceM, s.balanceAnnual, ''],
      ['Ahorro', s.savingsM, s.savingsAnnual, ''],
      ['Ganancia neta', s.profitM, s.profitAnnual, 'accent sep'],
    ];
    if (n > 1) {
      const perM = s.profitM.map((v) => v / n);
      rows.push(['Por persona (÷' + n + ')', perM, s.profitAnnual / n, 'split']);
    }
    summaryBody.innerHTML = '';
    let posMonths = 0;
    let negMonths = 0;
    rows.forEach(([label, months, annual, tone]) => {
      const tr = document.createElement('tr');
      const tones = String(tone || '')
        .split(/\s+/)
        .filter(Boolean);
      const isNet = tones.includes('accent');
      tones.forEach((t) => tr.classList.add('fp-sum-' + t));
      const th = document.createElement('th');
      th.textContent = label;
      tr.appendChild(th);
      months.forEach((v) => {
        const num = Number(v) || 0;
        const td = document.createElement('td');
        td.className = 'num';
        td.textContent = num ? formatMoney(num) : '—';
        if (isNet) {
          if (num > 0.009) {
            td.classList.add('amount-in');
            posMonths += 1;
          } else if (num < -0.009) {
            td.classList.add('amount-out');
            negMonths += 1;
          }
        } else if (num < 0) {
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
    const countEl = document.getElementById('fp-net-month-count');
    if (countEl) {
      const zeroMonths = Math.max(0, 12 - posMonths - negMonths);
      const pct = (n) => (n / 12) * 100;
      countEl.hidden = false;
      countEl.innerHTML =
        '<div class="fp-net-meter-head">' +
          '<span class="fp-net-meter-kicker">Ganancia neta</span>' +
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

  function renderRate() {
    if (!rateInput) return;
    const y = year();
    rateInput.value = String(Math.round((Number(y.savings_rate) || 0) * 1000) / 10);
  }

  function renderProductsIncome() {
    if (!productsIncomeBody) return;
    const y = year();
    if (!Array.isArray(y.income_months) || y.income_months.length !== 12) {
      y.income_months = emptyMonths();
    }
    productsIncomeBody.innerHTML = '';
    const tr = document.createElement('tr');
    const nameTd = document.createElement('td');
    nameTd.textContent = 'Ingresos totales';
    nameTd.className = 'fp-label-static';
    tr.appendChild(nameTd);
    for (let m = 0; m < 12; m++) {
      const td = document.createElement('td');
      td.className = 'num';
      const input = moneyInput(y.income_months[m] || 0);
      if (!readOnly) {
        input.addEventListener('change', () => {
          y.income_months[m] = parseNum(input.value);
          input.value = y.income_months[m] ? formatMoneyDec(y.income_months[m]) : '';
          markDirty();
          renderYearCompare();
          renderKpis();
          renderSummary();
          renderBurdenAndDaily();
          updateProductsTotal(tr, y.income_months);
        });
      }
      td.appendChild(input);
      tr.appendChild(td);
    }
    const totalTd = document.createElement('td');
    totalTd.className = 'num fp-row-total';
    tr.appendChild(totalTd);
    updateProductsTotal(tr, y.income_months);
    productsIncomeBody.appendChild(tr);
  }

  function updateProductsTotal(tr, months) {
    const td = tr.querySelector('.fp-row-total');
    if (!td) return;
    const sum = (months || []).reduce((a, b) => a + (Number(b) || 0), 0);
    td.textContent = sum ? formatMoneyDec(sum) : '—';
  }

  function renderTables() {
    const y = year();
    if (!Array.isArray(y.expenses)) y.expenses = [];
    if (clientsSection) clientsSection.classList.toggle('is-hidden', isProducts);
    if (productsSection) productsSection.classList.toggle('is-hidden', !isProducts);
    if (isProducts) {
      y.clients = [];
      renderProductsIncome();
    } else {
      if (!Array.isArray(y.clients)) y.clients = [];
      renderLineTable(clientsBody, y.clients, 'clients');
    }
    renderLineTable(expensesBody, y.expenses, 'expenses');
  }

  function renderBurdenAndDaily() {
    const s = stats();
    const y = year();
    const grid = 'rgba(15, 23, 42, 0.08)';
    const tick = '#64748b';

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

    const legendEl = document.getElementById('fp-burden-legend');
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

    const monthlyAvg = s.profitAnnual / 12;
    const dailyRef = monthlyAvg / 30;
    const yNum = planYearNum();
    const daysPerMonth = MONTHS.map((_, i) => daysInMonth(yNum, i));
    const dailyByMonth = daysPerMonth.map((d, i) => {
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

    whenChartReady(() => {
      if (burdenMonthsEl) {
        destroyChart('burdenMonths');
        const expenseColors = burdenMonths.map((m) => (m.expensePct >= 50 ? '#ef4444' : '#94a3b8'));
        charts.burdenMonths = new window.Chart(burdenMonthsEl, {
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
        charts.burdenTotal = new window.Chart(burdenTotalEl, {
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

        charts.burdenCategories = new window.Chart(burdenCategoriesEl, {
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

      if (dailyMonthsEl) {
        destroyChart('dailyMonths');
        const avgLine = MONTHS.map(() => Math.round(dailyRef * 100) / 100);
        charts.dailyMonths = new window.Chart(dailyMonthsEl, {
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
    });
  }

  function renderAll() {
    renderTabs();
    renderRate();
    syncSplitInput();
    renderYearCompare();
    renderKpis();
    renderTables();
    renderSummary();
    renderBurdenAndDaily();
  }

  async function save() {
    if (readOnly) return;
    workbook.activeYear = active;
    workbook.version = 2;
    workbook.template = template;
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
      setActiveYear(workbook.activeYear || 0);
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
      renderYearCompare();
      renderKpis();
      renderSummary();
      renderBurdenAndDaily();
    });
  }
  if (splitInput) {
    if (readOnly) {
      splitInput.disabled = true;
    } else {
      splitInput.addEventListener('change', () => {
        setSplitN(splitInput.value);
        syncSplitInput();
        markDirty();
        renderSummary();
      });
    }
  }
  if (!readOnly && btnSave) btnSave.addEventListener('click', save);
  if (!readOnly && btnAddClient) {
    btnAddClient.addEventListener('click', () => {
      if (isProducts) return;
      year().clients.push(emptyLine());
      markDirty();
      renderAll();
    });
  }
  if (!readOnly && btnAddExpense) {
    btnAddExpense.addEventListener('click', () => {
      year().expenses.push(emptyLine());
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

  if (!readOnly) {
    window.addEventListener('beforeunload', (ev) => {
      if (!dirty) return;
      ev.preventDefault();
      ev.returnValue = '';
    });
    document.addEventListener('keydown', (ev) => {
      if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
        ev.preventDefault();
        save();
      }
    });
  }

  renderAll();
  setStatus(readOnly ? 'Solo lectura' : 'Listo');
})();
