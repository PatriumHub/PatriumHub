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

  let active = clamp(workbook.activeYear || 0, 0, workbook.years.length - 1);
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

  function renderTabs() {
    if (!yearTabs) return;
    yearTabs.innerHTML = '';
    workbook.years.forEach((y, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fp-sheet-tab' + (i === active ? ' is-active' : '');
      btn.textContent = y.name || String(i + 1);
      btn.addEventListener('click', () => {
        active = i;
        workbook.activeYear = i;
        renderAll();
      });
      yearTabs.appendChild(btn);
    });
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
  let comparePieYear = 0;
  let chartWaitTimer = null;
  const chartReadyQueue = [];

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
    const prev = pieYearSelect.value;
    pieYearSelect.innerHTML = '';
    years.forEach((y, i) => {
      const opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = y.name || String(i + 1);
      pieYearSelect.appendChild(opt);
    });
    const idx = clamp(Number(prev || comparePieYear) || 0, 0, Math.max(0, years.length - 1));
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
      comparePieYear = Number(pieYearSelect.value) || 0;
      renderYearCompare();
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
    rows.forEach(([label, months, annual, tone]) => {
      const tr = document.createElement('tr');
      String(tone || '')
        .split(/\s+/)
        .filter(Boolean)
        .forEach((t) => tr.classList.add('fp-sum-' + t));
      const th = document.createElement('th');
      th.textContent = label;
      tr.appendChild(th);
      months.forEach((v) => {
        const td = document.createElement('td');
        td.className = 'num';
        td.textContent = v ? formatMoney(v) : '—';
        if (v < 0) td.classList.add('amount-out');
        tr.appendChild(td);
      });
      const tot = document.createElement('td');
      tot.className = 'num fp-row-total';
      tot.textContent = formatMoney(annual);
      tr.appendChild(tot);
      summaryBody.appendChild(tr);
    });
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

  function renderAll() {
    renderTabs();
    renderRate();
    syncSplitInput();
    renderYearCompare();
    renderKpis();
    renderTables();
    renderSummary();
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
      renderYearCompare();
      renderKpis();
      renderSummary();
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
    btnAddYear.addEventListener('click', () => {
      const last = workbook.years[workbook.years.length - 1];
      const guess = String((parseInt(last?.name, 10) || new Date().getFullYear()) + 1);
      const name = window.prompt('Año / nombre de la hoja', guess);
      if (!name) return;
      workbook.years.push(emptyYear(name.trim()));
      active = workbook.years.length - 1;
      workbook.activeYear = active;
      markDirty();
      renderAll();
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
