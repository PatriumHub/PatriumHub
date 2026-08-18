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
  const mixChartEl = document.getElementById('pp-mix-chart');
  const cumChartEl = document.getElementById('pp-cum-chart');
  const breakChartEl = document.getElementById('pp-break-chart');
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

  let active = clamp(workbook.activeYear || 0, 0, workbook.years.length - 1);
  let dirty = false;
  const charts = { flow: null, mix: null, cum: null, break: null };

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
    const savingsM = balanceM.map((v) => v * rate);
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
      btn.addEventListener('click', () => {
        active = i;
        workbook.activeYear = i;
        renderAll();
      });
      yearTabs.appendChild(btn);
    });
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
    const avgExp = s.expenseAnnual / 12;
    const runway = avgExp > 0.01 ? (liq / avgExp) : null;
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
        '<span class="pp-tile-label">Meses de pista</span>' +
        '<strong class="pp-tile-value">' + (runway != null ? runway.toFixed(1) + ' meses' : '—') + '</strong>' +
        '<span class="pp-tile-hint">Liquidez ÷ gasto mensual proyectado</span>' +
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
    rows.forEach(([label, months, annual, tone]) => {
      const tr = document.createElement('tr');
      String(tone || '').split(/\s+/).filter(Boolean).forEach((t) => tr.classList.add('fp-sum-' + t));
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

    if (mixChartEl) {
      destroyChart('mix');
      const income = Math.max(0, s.incomeAnnual);
      const expense = Math.max(0, s.expenseAnnual);
      charts.mix = new Chart(mixChartEl, {
        type: 'doughnut',
        data: {
          labels: ['Ingresos', 'Egresos'],
          datasets: [{
            data: income + expense > 0 ? [income, expense] : [1],
            backgroundColor: income + expense > 0
              ? ['rgba(16, 185, 129, 0.85)', 'rgba(239, 68, 68, 0.75)']
              : ['rgba(148, 163, 184, 0.35)'],
            borderWidth: 0,
            hoverOffset: 4,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: '#475569', boxWidth: 12, padding: 14 },
            },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const v = ctx.raw || 0;
                  const total = income + expense;
                  const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                  return ' ' + moneyLabel(v) + ' (' + pct + '%)';
                },
              },
            },
          },
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
    btnAddYear.addEventListener('click', () => {
      const last = workbook.years[workbook.years.length - 1];
      const n = Number(last?.name) || new Date().getFullYear();
      workbook.years.push(emptyYear(String(n + 1)));
      active = workbook.years.length - 1;
      workbook.activeYear = active;
      markDirty();
      renderAll();
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
