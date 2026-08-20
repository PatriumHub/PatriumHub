(() => {
  const themeKey = 'patrium-theme';
  const root = document.documentElement;
  const themeToggles = [...document.querySelectorAll('.theme-toggle')];

  const syncThemeLabel = () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    themeToggles.forEach((btn) => {
      btn.setAttribute('aria-label', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    });
  };
  syncThemeLabel();

  themeToggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(themeKey, next); } catch (_) {}
      syncThemeLabel();
    });
  });

  const reveal = () => {
    document.querySelectorAll('[data-skeleton]').forEach((el) => {
      el.classList.add('is-done');
    });
    document.querySelectorAll('[data-skeleton-target]').forEach((el) => {
      el.classList.add('is-ready');
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(reveal, 220));
  } else {
    window.setTimeout(reveal, 220);
  }

  document.addEventListener('click', (e) => {
    document.querySelectorAll('details.nav-drop[open]').forEach((d) => {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  const backToTop = document.getElementById('back-to-top');
  if (backToTop) {
    const threshold = 280;
    const syncBackToTop = () => {
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      backToTop.classList.toggle('is-visible', y > threshold);
    };
    syncBackToTop();
    window.addEventListener('scroll', syncBackToTop, { passive: true });
    backToTop.addEventListener('click', () => {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
  }

  const topbar = document.getElementById('topbar');
  const navToggle = document.getElementById('nav-toggle');
  if (topbar && navToggle) {
    const setOpen = (open) => {
      topbar.classList.toggle('is-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      navToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    };
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      setOpen(!topbar.classList.contains('is-open'));
    });
    topbar.querySelectorAll('.topbar-panel a').forEach((a) => {
      a.addEventListener('click', () => setOpen(false));
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 960) setOpen(false);
    });
  }

  const bootCharts = (attempt) => {
    attempt = attempt || 0;
    const node = document.getElementById('dashboard-charts')
      || document.getElementById('company-charts')
      || document.getElementById('accounts-charts')
      || document.getElementById('list-charts')
      || document.getElementById('mp-dashboard-charts')
      || document.getElementById('gastos-charts');
    if (!node) return;
    if (typeof Chart === 'undefined') {
      if (attempt < 50) window.setTimeout(() => bootCharts(attempt + 1), 100);
      return;
    }
    let data;
    try {
      data = JSON.parse(node.textContent || '{}');
    } catch (_) {
      return;
    }
    if (data.hide) return;

    const isDark = root.getAttribute('data-theme') === 'dark';
    const colors = isDark
      ? ['#10b981', '#60a5fa', '#34d399', '#f87171', '#a78bfa', '#22d3ee', '#fb923c']
      : ['#047857', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];
    const grid = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)';
    const tick = isDark ? '#94a3b8' : '#64748b';
    const base = data.baseUrl || (window.location.pathname.replace(/\/[^/]*$/, '') + '/index.php');

    const go = (route, params = {}) => {
      const q = new URLSearchParams({ r: route, ...params });
      window.location.href = `${base}?${q.toString()}`;
    };

    const moneyTick = (v) => {
      try {
        return new Intl.NumberFormat('es-AR', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
      } catch (_) {
        return String(v);
      }
    };

    const scaleOpts = {
      x: { ticks: { color: tick }, grid: { display: false } },
      y: { ticks: { color: tick, callback: moneyTick }, grid: { color: grid } },
    };

    const doughnutOpts = {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
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
      },
    };

    const comp = document.getElementById('chartComposition');
    if (comp && data.composition) {
      new Chart(comp, {
        type: 'doughnut',
        data: {
          labels: data.composition.labels,
          datasets: [{
            data: data.composition.values,
            backgroundColor: colors,
            borderWidth: 0,
            hoverOffset: 6,
          }],
        },
        options: {
          ...doughnutOpts,
          onClick: (_evt, elements) => {
            if (!elements.length || !data.composition.routes) return;
            const i = elements[0].index;
            const route = data.composition.routes[i];
            if (!route) return;
            const params = {};
            if (data.entityId) params.entity_id = String(data.entityId);
            go(route, params);
          },
        },
      });
    }

    const wealth = document.getElementById('chartWealthSplit');
    if (wealth && data.wealthSplit) {
      new Chart(wealth, {
        type: 'doughnut',
        data: {
          labels: data.wealthSplit.labels,
          datasets: [{
            data: data.wealthSplit.values,
            backgroundColor: ['#3b82f6', '#047857'],
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: doughnutOpts,
      });
    }

    const liqSrc = document.getElementById('chartLiqSources');
    if (liqSrc && data.liquiditySources) {
      new Chart(liqSrc, {
        type: 'doughnut',
        data: {
          labels: data.liquiditySources.labels,
          datasets: [{
            data: data.liquiditySources.values,
            backgroundColor: ['#10b981', '#06b6d4'],
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: doughnutOpts,
      });
    }

    const owns = document.getElementById('chartOwnerships');
    if (owns && data.ownerships && (data.ownerships.labels || []).length) {
      const fmtMoney = (v) => {
        try {
          return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: data.currency || 'ARS',
            maximumFractionDigits: 0,
          }).format(v);
        } catch (_) {
          return moneyTick(v);
        }
      };
      new Chart(owns, {
        type: 'bar',
        data: {
          labels: data.ownerships.labels,
          datasets: [{
            label: 'Valor atribuido',
            data: data.ownerships.values,
            backgroundColor: (data.ownerships.values || []).map((_, i) => colors[i % colors.length]),
            borderRadius: 10,
            barPercentage: 0.72,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#ffffff',
              titleColor: '#1a202c',
              bodyColor: '#475569',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              callbacks: {
                afterTitle: (items) => {
                  const i = items[0]?.dataIndex ?? 0;
                  const pct = (data.ownerships.pcts || [])[i];
                  const liq = (data.ownerships.liquidity || [])[i];
                  const bits = [];
                  if (pct != null) bits.push(`Participación ${pct}%`);
                  if (liq != null) bits.push(`Liquidez ${fmtMoney(liq)}`);
                  return bits.join(' · ');
                },
                label: (item) => ` ${fmtMoney(item.parsed.x)}`,
              },
            },
          },
          scales: {
            x: {
              ticks: { color: tick, callback: moneyTick },
              grid: { color: grid },
              beginAtZero: true,
            },
            y: {
              ticks: { color: '#1a202c', font: { size: 13, weight: '500' } },
              grid: { display: false },
            },
          },
          onClick: (_evt, elements) => {
            if (!elements.length || !data.ownerships.ids) return;
            const id = data.ownerships.ids[elements[0].index];
            if (!id) return;
            go(`/empresas/${id}`, { tab: 'resumen' });
          },
        },
      });
    }

    const liq = document.getElementById('chartLiquidity');
    if (liq && data.liquidity) {
      new Chart(liq, {
        type: 'doughnut',
        data: {
          labels: data.liquidity.labels,
          datasets: [{
            data: data.liquidity.values,
            backgroundColor: ['#047857', '#e2e8f0'],
            borderWidth: 0,
            hoverOffset: 6,
          }],
        },
        options: {
          ...doughnutOpts,
          onClick: (_evt, elements) => {
            if (!elements.length) return;
            go(elements[0].index === 0 ? '/cuentas' : '/activos',
              data.entityId ? { entity_id: String(data.entityId) } : {});
          },
        },
      });
    }

    const cur = document.getElementById('chartCurrency');
    if (cur && data.currencySplit) {
      new Chart(cur, {
        type: 'bar',
        data: {
          labels: data.currencySplit.labels,
          datasets: [{
            label: 'Bruto',
            data: data.currencySplit.values,
            backgroundColor: '#3b82f6',
            borderRadius: 6,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: scaleOpts,
        },
      });
    }

    const ent = document.getElementById('chartEntities');
    if (ent && data.entities) {
      new Chart(ent, {
        type: 'bar',
        data: {
          labels: data.entities.labels,
          datasets: [{
            label: 'Patrimonio neto',
            data: data.entities.values,
            backgroundColor: '#047857',
            borderRadius: 6,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: scaleOpts,
          onClick: (_evt, elements) => {
            if (!elements.length || !data.entities.ids) return;
            const i = elements[0].index;
            const id = data.entities.ids[i];
            const type = (data.entities.types && data.entities.types[i]) || 'company';
            if (!id) return;
            go(type === 'person' ? `/personas/${id}` : `/empresas/${id}`,
              { tab: 'resumen' });
          },
        },
      });
    }

    const evo = document.getElementById('chartEvolution');
    if (evo && data.evolution) {
      const fmtMoney = (v) => {
        try {
          return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: data.currency || 'ARS',
            maximumFractionDigits: 0,
          }).format(v);
        } catch (_) {
          return moneyTick(v);
        }
      };
      const evoLabels = (data.evolution.labels || []).map((raw) => {
        const s = String(raw);
        // 2026-07-31 → 31/07
        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}/${m[2]}` : s;
      });
      const pointCount = evoLabels.length;
      const showPoints = pointCount <= 24;
      const simple = !!data.evolution.simple;
      const datasets = [
        {
          label: 'Patrimonio neto',
          data: data.evolution.net,
          borderColor: '#34d399',
          backgroundColor: (ctx) => {
            const chart = ctx.chart;
            const { ctx: c, chartArea } = chart;
            if (!chartArea) return 'rgba(240, 193, 75, 0.15)';
            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, 'rgba(240, 193, 75, 0.35)');
            g.addColorStop(1, 'rgba(240, 193, 75, 0.02)');
            return g;
          },
          fill: true,
          tension: 0.3,
          borderWidth: 3.5,
          pointRadius: showPoints ? 4 : 0,
          pointHoverRadius: 6,
          pointBackgroundColor: '#34d399',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          order: 1,
        },
      ];
      if (simple && (data.evolution.liquidity || []).length) {
        datasets.push({
          label: 'Liquidez',
          data: data.evolution.liquidity,
          borderColor: '#10b981',
          backgroundColor: 'transparent',
          fill: false,
          tension: 0.3,
          borderWidth: 2.5,
          pointRadius: showPoints ? 3 : 0,
          pointHoverRadius: 5,
          pointBackgroundColor: '#10b981',
          order: 2,
        });
      } else if (!simple) {
        datasets.push(
          {
            label: 'Activos',
            data: data.evolution.assets,
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: showPoints ? 3 : 0,
            pointHoverRadius: 5,
            pointBackgroundColor: '#10b981',
            order: 2,
          },
          {
            label: 'Pasivos',
            data: data.evolution.liabilities,
            borderColor: '#ef4444',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: showPoints ? 3 : 0,
            pointHoverRadius: 5,
            pointBackgroundColor: '#ef4444',
            order: 3,
          }
        );
      }

      new Chart(evo, {
        type: 'line',
        data: {
          labels: evoLabels,
          datasets,
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#1a202c',
                boxWidth: 14,
                boxHeight: 3,
                padding: 16,
                font: { size: 13, weight: '500' },
                usePointStyle: false,
              },
            },
            tooltip: {
              backgroundColor: '#ffffff',
              titleColor: '#1a202c',
              bodyColor: '#475569',
              borderColor: '#e2e8f0',
              borderWidth: 1,
              padding: 12,
              callbacks: {
                title: (items) => {
                  const i = items[0]?.dataIndex ?? 0;
                  return data.evolution.labels[i] || '';
                },
                label: (item) => ` ${item.dataset.label}: ${fmtMoney(item.parsed.y)}`,
              },
            },
          },
          scales: {
            x: {
              ticks: {
                color: '#475569',
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 10,
                font: { size: 12 },
              },
              grid: { color: 'rgba(15, 23, 42, 0.05)', drawBorder: false },
            },
            y: {
              ticks: {
                color: '#475569',
                callback: moneyTick,
                font: { size: 12 },
                padding: 8,
              },
              grid: { color: 'rgba(15, 23, 42, 0.1)', drawBorder: false },
              beginAtZero: true,
            },
          },
        },
      });
    }

    const flow = document.getElementById('chartFlow');
    if (flow && data.flow && Array.isArray(data.flow.values)) {
      new Chart(flow, {
        type: 'bar',
        data: {
          labels: data.flow.labels,
          datasets: [{
            data: data.flow.values,
            backgroundColor: ['#10b981', '#ef4444'],
            borderRadius: 8,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: scaleOpts,
          onClick: () => go('/movimientos', data.entityId ? { entity_id: String(data.entityId) } : {}),
        },
      });
    }

    const rank = document.getElementById('chartRanking');
    if (rank && data.ranking) {
      new Chart(rank, {
        type: 'bar',
        data: {
          labels: data.ranking.labels,
          datasets: [{
            label: 'Δ neto',
            data: data.ranking.values,
            backgroundColor: (data.ranking.values || []).map((v) => (v >= 0 ? '#10b981' : '#ef4444')),
            borderRadius: 6,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { color: tick, callback: moneyTick }, grid: { color: grid } },
            y: { ticks: { color: tick }, grid: { display: false } },
          },
          onClick: (_evt, elements) => {
            if (!elements.length || !data.ranking.ids) return;
            const id = data.ranking.ids[elements[0].index];
            if (!id) return;
            go('/dashboard', { mode: 'entity', entity_id: String(id), currency: data.currency || 'ARS' });
          },
        },
      });
    }

    const makeSalesBar = (el, series, color) => {
      if (!el || !series || !(series.labels || []).length) return;
      const values = series.sales || [];
      if (!values.some((v) => Math.abs(Number(v)) > 0.009)) return;
      new Chart(el, {
        type: 'bar',
        data: {
          labels: series.labels,
          datasets: [{
            label: 'Ventas',
            data: values,
            backgroundColor: values.map((v) => (Number(v) >= 0 ? color : '#ef4444')),
            borderRadius: 6,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: scaleOpts,
        },
      });
    };

    makeSalesBar(document.getElementById('chartWooSales'), data.wooSales, '#047857');
    makeSalesBar(document.getElementById('chartMpSales'), data.mpSales, '#3b82f6');
    makeSalesBar(document.getElementById('chartCashSales'), data.cashSales, '#f97316');

    const accountsByType = document.getElementById('chartAccountsByType')
      || document.getElementById('chartListByType');
    if (accountsByType && data.byType) {
      const typeVals = (data.byType.values || []).map(Number);
      const typeSum = typeVals.reduce((a, b) => a + Math.abs(b), 0);
      new Chart(accountsByType, {
        type: 'doughnut',
        data: {
          labels: data.byType.labels,
          datasets: [{
            data: typeSum > 0 ? typeVals : [1],
            backgroundColor: typeSum > 0 ? colors : ['rgba(148,163,184,0.35)'],
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: doughnutOpts,
      });
    }

    const accountsByEntity = document.getElementById('chartAccountsByEntity')
      || document.getElementById('chartListByEntity');
    if (accountsByEntity && data.byEntity) {
      const entLabels = data.byEntity.labels || [];
      const entVals = (data.byEntity.values || []).map(Number);
      new Chart(accountsByEntity, {
        type: 'bar',
        data: {
          labels: entLabels.length ? entLabels : ['Sin datos'],
          datasets: [{
            label: data.unit === 'pct' ? '%' : (data.unit === 'count' ? 'Cantidad' : 'Valor'),
            data: entVals.length ? entVals : [0],
            backgroundColor: '#3b82f6',
            borderRadius: 8,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              ticks: {
                color: tick,
                callback: data.unit === 'money' ? moneyTick : (v) => v,
              },
              grid: { color: grid },
              beginAtZero: true,
            },
            y: { ticks: { color: '#1a202c' }, grid: { display: false } },
          },
        },
      });
    }

    const mpByAccount = document.getElementById('chartMpByAccount');
    if (mpByAccount && data.byAccount) {
      new Chart(mpByAccount, {
        type: 'doughnut',
        data: {
          labels: data.byAccount.labels,
          datasets: [{
            data: data.byAccount.values,
            backgroundColor: colors,
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: {
          ...doughnutOpts,
          onClick: (_evt, elements) => {
            if (!elements.length || !data.byAccount.ids) return;
            const id = data.byAccount.ids[elements[0].index];
            if (!id) return;
            go(`/cuentas/${id}/editar`);
          },
        },
      });
    }

    const mpByEntity = document.getElementById('chartMpByEntity')
      || document.getElementById('chartAccountsMpByEntity');
    const mpEntitySeries = data.mpByEntity || (document.getElementById('chartMpByEntity') ? data.byEntity : null);
    if (mpByEntity && mpEntitySeries) {
      new Chart(mpByEntity, {
        type: 'bar',
        data: {
          labels: mpEntitySeries.labels,
          datasets: [{
            label: 'Saldo',
            data: mpEntitySeries.values,
            backgroundColor: '#8b5cf6',
            borderRadius: 8,
          }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { color: tick, callback: moneyTick }, grid: { color: grid }, beginAtZero: true },
            y: { ticks: { color: '#1a202c' }, grid: { display: false } },
          },
        },
      });
    }

    const salesSplit = document.getElementById('chartSalesSplit');
    if (salesSplit && data.salesSplit) {
      new Chart(salesSplit, {
        type: 'doughnut',
        data: {
          labels: data.salesSplit.labels,
          datasets: [{
            data: data.salesSplit.values,
            backgroundColor: ['#047857', '#3b82f6', '#f97316'],
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: '#64748b', boxWidth: 12 },
            },
          },
        },
      });
    }

    const company = document.getElementById('chartCompanyExtras');
    if (company && data.companyExtras) {
      new Chart(company, {
        type: 'bar',
        data: {
          labels: data.companyExtras.labels,
          datasets: [{
            data: data.companyExtras.values,
            backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#047857'],
            borderRadius: 8,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: scaleOpts,
        },
      });
    }

    const gastosMonth = document.getElementById('chartGastosMonth');
    if (gastosMonth && data.byMonth && !data.hide) {
      new Chart(gastosMonth, {
        type: 'line',
        data: {
          labels: data.byMonth.labels || [],
          datasets: [
            {
              label: 'Ingresos',
              data: data.byMonth.income || [],
              borderColor: '#10b981',
              backgroundColor: 'rgba(16,185,129,0.12)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'Egresos',
              data: data.byMonth.expense || [],
              borderColor: '#f87171',
              backgroundColor: 'rgba(248,113,113,0.12)',
              tension: 0.25,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: tick, boxWidth: 12 } },
          },
          scales: scaleOpts,
        },
      });
    }

    const barPalette = ['#f87171', '#fb923c', '#fbbf24', '#a78bfa', '#60a5fa', '#22d3ee', '#34d399', '#f472b6'];
    const sizeHbarWrap = (el, n) => {
      const wrap = el && el.parentElement;
      if (!wrap || !wrap.classList.contains('gastos-hbar')) return;
      const rows = Math.max(1, Number(n) || 1);
      wrap.style.height = Math.min(520, Math.max(240, 48 + rows * 34)) + 'px';
    };
    const makeExpenseBar = (el, series, horizontal) => {
      if (!el || !series || data.hide || !(series.labels || []).length) return;
      if (horizontal) sizeHbarWrap(el, series.labels.length);
      const hScales = horizontal
        ? {
            x: { ticks: { color: tick, callback: moneyTick }, grid: { color: grid } },
            y: {
              ticks: { color: tick, autoSkip: false, font: { size: 12 } },
              grid: { display: false },
            },
          }
        : scaleOpts;
      new Chart(el, {
        type: 'bar',
        data: {
          labels: series.labels,
          datasets: [{
            data: series.values,
            backgroundColor: series.labels.map((_, i) => barPalette[i % barPalette.length]),
            borderRadius: 8,
            barPercentage: 0.78,
            categoryPercentage: 0.82,
          }],
        },
        options: {
          indexAxis: horizontal ? 'y' : 'x',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: hScales,
          layout: horizontal ? { padding: { left: 4, right: 8 } } : undefined,
        },
      });
    };
    makeExpenseBar(document.getElementById('chartGastosEntity'), data.byEntity, true);
    makeExpenseBar(document.getElementById('chartGastosCategory'), data.byCategory, true);
    makeExpenseBar(document.getElementById('chartGastosAccount'), data.byAccount, true);

    const catMonthEl = document.getElementById('chartGastosCategoryMonth');
    const catMonth = data.byCategoryMonth;
    if (catMonthEl && catMonth && !data.hide && (catMonth.labels || []).length) {
      const moneyFmt = (v) => {
        try {
          return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: data.currency || 'ARS',
            maximumFractionDigits: 0,
          }).format(v);
        } catch (_) {
          return String(v);
        }
      };
      const monthShort = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
      const monthLabels = (catMonth.labels || []).map((ym) => {
        const m = String(ym).match(/^(\d{4})-(\d{2})$/);
        if (!m) return String(ym);
        const mi = Math.max(0, Math.min(11, parseInt(m[2], 10) - 1));
        return monthShort[mi] + ' ' + m[1].slice(2);
      });
      const series = catMonth.series || [];
      new Chart(catMonthEl, {
        type: 'bar',
        data: {
          labels: monthLabels,
          datasets: series.length
            ? series.map((s) => ({
                label: s.name,
                data: s.values || [],
                backgroundColor: s.color,
                borderWidth: 0,
                borderRadius: 3,
                stack: 'gastos',
                pcts: s.pcts || [],
                barPercentage: 0.85,
                categoryPercentage: 0.9,
              }))
            : [{
                label: 'Sin egresos',
                data: (catMonth.labels || []).map(() => 0),
                backgroundColor: 'rgba(148,163,184,0.25)',
                borderWidth: 0,
                stack: 'gastos',
                pcts: [],
              }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: tick, boxWidth: 12, padding: 14 },
            },
            tooltip: {
              callbacks: {
                footer: (items) => {
                  if (!items.length) return '';
                  const i = items[0].dataIndex;
                  const total = (catMonth.month_totals || [])[i] || 0;
                  return 'Total mes: ' + moneyFmt(total);
                },
                label: (ctx) => {
                  const val = Number(ctx.raw) || 0;
                  const pct = (ctx.dataset.pcts || [])[ctx.dataIndex];
                  const pctTxt = pct == null ? '—' : String(pct).replace('.', ',') + '%';
                  return ' ' + ctx.dataset.label + ': ' + moneyFmt(val) + ' (' + pctTxt + ')';
                },
              },
            },
          },
          scales: {
            x: {
              stacked: true,
              ticks: { color: tick, callback: moneyTick },
              grid: { color: grid },
            },
            y: {
              stacked: true,
              ticks: { color: tick, autoSkip: false, font: { size: 12 } },
              grid: { display: false },
            },
          },
        },
      });
    }

    const gastosType = document.getElementById('chartGastosType');
    if (gastosType && data.byType && !data.hide && (data.byType.labels || []).length) {
      new Chart(gastosType, {
        type: 'doughnut',
        data: {
          labels: data.byType.labels,
          datasets: [{
            data: data.byType.values,
            backgroundColor: ['#f87171', '#fb923c', '#a78bfa'],
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: tick, boxWidth: 12 } },
          },
        },
      });
    }

    const gastosSplit = document.getElementById('chartGastosSplit');
    if (gastosSplit && data.splitBudget && !data.hide) {
      new Chart(gastosSplit, {
        type: 'doughnut',
        data: {
          labels: data.splitBudget.labels || [],
          datasets: [{
            data: data.splitBudget.values || [],
            backgroundColor: ['#fbbf24', '#64748b'],
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: tick, boxWidth: 12 } },
          },
        },
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(bootCharts, 50));
  } else {
    window.setTimeout(bootCharts, 50);
  }
})();
