/**
 * Proyecciones consolidadas â€” flujo arriba, ahorro abajo.
 * Paleta unificada: verde ingresos / coral egresos / verde marca para ahorro.
 */
(function () {
  'use strict';

  const raw = document.getElementById('cp-charts-data');
  if (!raw || typeof Chart === 'undefined') return;

  let payload;
  try {
    payload = JSON.parse(raw.textContent || '{}');
  } catch (_) {
    return;
  }
  if (payload.hide) return;

  const months = payload.months || [];
  const charts = payload.charts || {};
  const currency = (payload.currency || 'ARS').toUpperCase();
  const grid = 'rgba(15, 23, 42, 0.08)';
  const tick = '#64748b';
  const GREEN = '#10b981';
  const GREEN_INK = '#047857';
  const CORAL = '#ef4444';
  const savingsPalette = ['#047857', '#10b981', '#34d399', '#059669', '#6ee7b7', '#a7f3d0'];

  function moneyLabel(v) {
    try {
      return currency + ' ' + new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v || 0));
    } catch (_) {
      return currency + ' ' + String(Math.round(v || 0));
    }
  }

  function compact(v) {
    try {
      return new Intl.NumberFormat('es-AR', { notation: 'compact' }).format(v);
    } catch (_) {
      return v;
    }
  }

  const axis = {
    x: { ticks: { color: tick }, grid: { display: false } },
    y: {
      ticks: { color: tick, callback: compact },
      grid: { color: grid },
    },
  };

  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#475569', boxWidth: 12 } } },
    scales: axis,
  };

  function mount(id, config) {
    const el = document.getElementById(id);
    if (!el) return;
    try {
      new Chart(el, config);
    } catch (err) {
      console.error('Chart ' + id, err);
    }
  }

  mount('cp-flow-chart', {
    type: 'bar',
    data: {
      labels: months,
      datasets: [
        {
          label: 'Ingresos',
          data: charts.income_months || [],
          backgroundColor: 'rgba(16, 185, 129, 0.75)',
          borderRadius: 4,
        },
        {
          label: 'Egresos',
          data: charts.expense_months || [],
          backgroundColor: 'rgba(239, 68, 68, 0.65)',
          borderRadius: 4,
        },
        {
          label: 'Balance',
          data: charts.balance_months || [],
          type: 'line',
          borderColor: GREEN_INK,
          backgroundColor: 'transparent',
          tension: 0.25,
          pointRadius: 3,
          pointBackgroundColor: GREEN_INK,
          pointBorderColor: '#fff',
        },
      ],
    },
    options: baseOpts,
  });

  (function () {
    const g = charts.groups || { labels: [], income: [], expense: [] };
    mount('cp-groups-chart', {
      type: 'bar',
      data: {
        labels: g.labels || [],
        datasets: [
          {
            label: 'Ingresos',
            data: g.income || [],
            backgroundColor: 'rgba(16, 185, 129, 0.75)',
            borderRadius: 4,
          },
          {
            label: 'Egresos',
            data: g.expense || [],
            backgroundColor: 'rgba(239, 68, 68, 0.65)',
            borderRadius: 4,
          },
        ],
      },
      options: baseOpts,
    });
  })();

  (function () {
    const be = charts.by_entity || { labels: [], income: [], expense: [] };
    const labels = be.labels || [];
    mount('cp-entity-chart', {
      type: 'bar',
      data: {
        labels: labels.length ? labels : ['Sin datos'],
        datasets: [
          {
            label: 'Ingresos',
            data: labels.length ? (be.income || []) : [0],
            backgroundColor: 'rgba(16, 185, 129, 0.75)',
            borderRadius: 4,
          },
          {
            label: 'Egresos',
            data: labels.length ? (be.expense || []) : [0],
            backgroundColor: 'rgba(239, 68, 68, 0.65)',
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: labels.length > 5 ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#475569', boxWidth: 12 } },
          tooltip: {
            callbacks: { label: (ctx) => ' ' + moneyLabel(ctx.raw || 0) },
          },
        },
        scales: labels.length > 5
          ? {
              x: { ticks: { color: tick, callback: compact }, grid: { color: grid }, beginAtZero: true },
              y: { ticks: { color: '#1a202c' }, grid: { display: false } },
            }
          : axis,
      },
    });
  })();

  mount('cp-savings-chart', {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        label: 'Ahorro mensual',
        data: charts.savings_months || [],
        borderColor: GREEN_INK,
        backgroundColor: 'rgba(4, 120, 87, 0.12)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: GREEN_INK,
        pointBorderColor: '#fff',
      }],
    },
    options: {
      ...baseOpts,
      plugins: { legend: { display: false } },
    },
  });

  mount('cp-savings-cum-chart', {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        label: 'Ahorro acumulado',
        data: charts.savings_cum || [],
        borderColor: GREEN,
        backgroundColor: 'rgba(16, 185, 129, 0.14)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: GREEN,
        pointBorderColor: '#fff',
      }],
    },
    options: {
      ...baseOpts,
      plugins: { legend: { display: false } },
    },
  });

  (function () {
    const sm = charts.savings_mix || { labels: [], values: [] };
    const labels = sm.labels || [];
    const vals = (sm.values || []).map((v) => Math.max(0, Number(v) || 0));
    const total = vals.reduce((a, b) => a + b, 0);
    const bg = labels.map((_, i) => savingsPalette[i % savingsPalette.length]);
    mount('cp-savings-mix-chart', {
      type: 'doughnut',
      data: {
        labels: labels.length ? labels : ['Sin ahorro'],
        datasets: [{
          data: total > 0 ? vals : [1],
          backgroundColor: total > 0 ? bg : ['rgba(148, 163, 184, 0.35)'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { position: 'bottom', labels: { color: '#475569', boxWidth: 10, font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.raw || 0;
                const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                return ' ' + moneyLabel(v) + ' (' + pct + '%)';
              },
            },
          },
        },
      },
    });
  })();

  (function () {
    const incomeM = charts.income_months || [];
    const expenseM = charts.expense_months || [];
    const savingsM = charts.savings_months || [];
    const profitM = charts.profit_months || [];
    const expensePct = incomeM.map((inc, i) => {
      const income = Number(inc) || 0;
      const expense = Math.max(0, Number(expenseM[i]) || 0);
      if (income <= 0.009) return 0;
      if (expense >= income - 0.009) return 100;
      return Math.round((expense / income) * 1000) / 10;
    });
    const savingsPct = incomeM.map((inc, i) => {
      const income = Number(inc) || 0;
      const expense = Math.max(0, Number(expenseM[i]) || 0);
      const savings = Math.max(0, Number(savingsM[i]) || 0);
      if (income <= 0.009 || expense >= income - 0.009) return 0;
      return Math.round((Math.min(savings, income - expense) / income) * 1000) / 10;
    });
    const restPct = incomeM.map((inc, i) => {
      const income = Number(inc) || 0;
      if (income <= 0.009) return 0;
      const ep = expensePct[i] || 0;
      const sp = savingsPct[i] || 0;
      return Math.max(0, Math.round((1000 - ep * 10 - sp * 10)) / 10);
    });
    const expenseColors = expensePct.map((p) => (p >= 50 ? '#ef4444' : '#94a3b8'));
    mount('cp-burden-months', {
      type: 'bar',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Egresos',
            data: expensePct,
            backgroundColor: expenseColors,
            borderRadius: 4,
            stack: 'burden',
            barPercentage: 0.78,
            categoryPercentage: 0.82,
          },
          {
            label: 'Ahorro',
            data: savingsPct,
            backgroundColor: '#0ea5e9',
            borderRadius: 4,
            stack: 'burden',
            barPercentage: 0.78,
            categoryPercentage: 0.82,
          },
          {
            label: 'Disponible neto',
            data: restPct,
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
                const inc = Number(incomeM[i]) || 0;
                if (inc <= 0.009) return ' Sin ingresos este mes';
                const idx = ctx.datasetIndex;
                const amt = idx === 0
                  ? (Number(expenseM[i]) || 0)
                  : (idx === 1 ? Math.max(0, Number(savingsM[i]) || 0) : Math.max(0, Number(profitM[i]) || 0));
                const pct = idx === 0 ? expensePct[i] : (idx === 1 ? savingsPct[i] : restPct[i]);
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
  })();

  (function () {
    const bt = charts.burden_total || {};
    const income = Math.max(0, Number(bt.income) || 0);
    const expense = Math.max(0, Number(bt.expense) || 0);
    const savings = Math.max(0, Number(bt.savings) || 0);
    const rest = Math.max(0, Number(bt.rest) || 0);
    mount('cp-burden-total', {
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
                const p = Math.round((v / income) * 1000) / 10;
                return ' ' + moneyLabel(v) + ' · ' + p.toLocaleString('es-AR') + '%';
              },
            },
          },
        },
      },
    });
  })();

  (function () {
    const g = charts.groups || { labels: [], burden_pct: [] };
    const vals = (g.burden_pct || [0, 0]).map((v) => Number(v) || 0);
    mount('cp-burden-groups', {
      type: 'bar',
      data: {
        labels: g.labels || ['Personas', 'Empresas'],
        datasets: [{
          label: '% egresos / ingresos',
          data: vals,
          backgroundColor: ['#3b82f6', '#64748b'],
          borderRadius: 6,
          barPercentage: 0.55,
          categoryPercentage: 0.65,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ' ' + (Number(ctx.raw) || 0).toLocaleString('es-AR') + '%',
            },
          },
        },
        scales: {
          x: { ticks: { color: tick }, grid: { display: false } },
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { color: tick, callback: (v) => v + '%' },
            grid: { color: grid },
          },
        },
      },
    });
  })();

  (function () {
    const be = charts.by_entity || { labels: [], burden_pct: [] };
    const labels = be.labels || [];
    const vals = be.burden_pct || [];
    const ranked = labels.map((name, i) => ({
      name,
      pct: Number(vals[i]) || 0,
    })).sort((a, b) => b.pct - a.pct);
    const rowH = Math.max(180, 48 + ranked.length * 36);
    const entityEl = document.getElementById('cp-burden-entity');
    if (entityEl && entityEl.parentElement) {
      entityEl.parentElement.style.height = rowH + 'px';
    }
    mount('cp-burden-entity', {
      type: 'bar',
      data: {
        labels: ranked.length ? ranked.map((r) => r.name) : ['Sin datos'],
        datasets: [{
          label: '% egresos / ingresos',
          data: ranked.length ? ranked.map((r) => r.pct) : [0],
          backgroundColor: ranked.length
            ? ranked.map((r) => (r.pct >= 50 ? '#ef4444' : '#94a3b8'))
            : ['#cbd5e1'],
          borderRadius: 5,
          barPercentage: 0.65,
          categoryPercentage: 0.75,
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
              label: (ctx) => ' ' + (Number(ctx.raw) || 0).toLocaleString('es-AR') + '%',
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            max: 100,
            ticks: { color: tick, callback: (v) => v + '%' },
            grid: { color: grid },
          },
          y: { ticks: { color: tick }, grid: { display: false } },
        },
      },
    });
  })();

  (function () {
    const profitAnnual = Number(payload.profit_annual) || 0;
    const monthlyAvg = profitAnnual / 12;
    const dailyRef = monthlyAvg / 30;
    const yNum = Number(payload.year) || new Date().getFullYear();
    const daysPerMonth = months.map((_, i) => new Date(yNum, i + 1, 0).getDate());
    const profitM = charts.profit_months || [];
    const dailyByMonth = daysPerMonth.map((d, i) => {
      const resto = Number(profitM[i]) || 0;
      return d > 0 ? Math.round((resto / d) * 100) / 100 : 0;
    });
    const avgLine = months.map(() => Math.round(dailyRef * 100) / 100);

    mount('cp-daily-months', {
      type: 'bar',
      data: {
        labels: months,
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
            borderColor: GREEN_INK,
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
        scales: axis,
      },
    });
  })();
})();
