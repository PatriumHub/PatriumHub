/**
 * Proyecciones consolidadas — flujo arriba, ahorro abajo.
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
    const mix = charts.mix || { labels: [], values: [] };
    const vals = (mix.values || []).map(Number);
    const total = vals.reduce((a, b) => a + Math.max(0, b), 0);
    mount('cp-mix-chart', {
      type: 'doughnut',
      data: {
        labels: mix.labels || ['Ingresos', 'Egresos'],
        datasets: [{
          data: total > 0 ? vals : [1],
          backgroundColor: total > 0
            ? ['rgba(16, 185, 129, 0.85)', 'rgba(239, 68, 68, 0.75)']
            : ['rgba(148, 163, 184, 0.35)'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { position: 'bottom', labels: { color: '#475569', boxWidth: 12 } },
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
})();
