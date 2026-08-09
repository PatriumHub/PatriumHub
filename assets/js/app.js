(() => {
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

  const bootCharts = () => {
    const node = document.getElementById('dashboard-charts')
      || document.getElementById('company-charts')
      || document.getElementById('accounts-charts')
      || document.getElementById('mp-dashboard-charts');
    if (!node || typeof Chart === 'undefined') return;
    let data;
    try {
      data = JSON.parse(node.textContent || '{}');
    } catch (_) {
      return;
    }
    if (data.hide) return;

    const colors = [
      '#d4a017', '#5b8def', '#7fd99a', '#f07178', '#c084fc', '#38bdf8', '#fb923c',
    ];
    const grid = 'rgba(232, 234, 239, 0.06)';
    const tick = '#8b93a7';
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
            color: '#c9d0de',
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
            backgroundColor: ['#5b8def', '#d4a017'],
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
            backgroundColor: ['#7fd99a', '#38bdf8'],
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
              backgroundColor: '#161a22',
              titleColor: '#e8eaef',
              bodyColor: '#c9d0de',
              borderColor: '#2a3140',
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
              ticks: { color: '#e8eaef', font: { size: 13, weight: '500' } },
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
            backgroundColor: ['#d4a017', '#2a3140'],
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
            backgroundColor: '#5b8def',
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
            backgroundColor: '#d4a017',
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
          borderColor: '#f0c14b',
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
          pointBackgroundColor: '#f0c14b',
          pointBorderColor: '#0c0e12',
          pointBorderWidth: 2,
          order: 1,
        },
      ];
      if (simple && (data.evolution.liquidity || []).length) {
        datasets.push({
          label: 'Liquidez',
          data: data.evolution.liquidity,
          borderColor: '#7fd99a',
          backgroundColor: 'transparent',
          fill: false,
          tension: 0.3,
          borderWidth: 2.5,
          pointRadius: showPoints ? 3 : 0,
          pointHoverRadius: 5,
          pointBackgroundColor: '#7fd99a',
          order: 2,
        });
      } else if (!simple) {
        datasets.push(
          {
            label: 'Activos',
            data: data.evolution.assets,
            borderColor: '#7fd99a',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: showPoints ? 3 : 0,
            pointHoverRadius: 5,
            pointBackgroundColor: '#7fd99a',
            order: 2,
          },
          {
            label: 'Pasivos',
            data: data.evolution.liabilities,
            borderColor: '#f07178',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: showPoints ? 3 : 0,
            pointHoverRadius: 5,
            pointBackgroundColor: '#f07178',
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
                color: '#e8eaef',
                boxWidth: 14,
                boxHeight: 3,
                padding: 16,
                font: { size: 13, weight: '500' },
                usePointStyle: false,
              },
            },
            tooltip: {
              backgroundColor: '#161a22',
              titleColor: '#e8eaef',
              bodyColor: '#c9d0de',
              borderColor: '#2a3140',
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
                color: '#c9d0de',
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 10,
                font: { size: 12 },
              },
              grid: { color: 'rgba(232, 234, 239, 0.04)', drawBorder: false },
            },
            y: {
              ticks: {
                color: '#c9d0de',
                callback: moneyTick,
                font: { size: 12 },
                padding: 8,
              },
              grid: { color: 'rgba(232, 234, 239, 0.08)', drawBorder: false },
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
            backgroundColor: ['#7fd99a', '#f07178'],
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
            backgroundColor: (data.ranking.values || []).map((v) => (v >= 0 ? '#7fd99a' : '#f07178')),
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
            backgroundColor: values.map((v) => (Number(v) >= 0 ? color : '#f07178')),
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

    makeSalesBar(document.getElementById('chartWooSales'), data.wooSales, '#d4a017');
    makeSalesBar(document.getElementById('chartMpSales'), data.mpSales, '#5b8def');
    makeSalesBar(document.getElementById('chartCashSales'), data.cashSales, '#fb923c');

    const accountsByType = document.getElementById('chartAccountsByType');
    if (accountsByType && data.byType) {
      new Chart(accountsByType, {
        type: 'doughnut',
        data: {
          labels: data.byType.labels,
          datasets: [{
            data: data.byType.values,
            backgroundColor: colors,
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: doughnutOpts,
      });
    }

    const accountsByEntity = document.getElementById('chartAccountsByEntity');
    if (accountsByEntity && data.byEntity) {
      new Chart(accountsByEntity, {
        type: 'bar',
        data: {
          labels: data.byEntity.labels,
          datasets: [{
            label: 'Saldo',
            data: data.byEntity.values,
            backgroundColor: '#5b8def',
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
            y: { ticks: { color: '#e8eaef' }, grid: { display: false } },
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
            backgroundColor: '#c084fc',
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
            y: { ticks: { color: '#e8eaef' }, grid: { display: false } },
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
            backgroundColor: ['#d4a017', '#5b8def', '#fb923c'],
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: '#8b93a7', boxWidth: 12 },
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
            backgroundColor: ['#7fd99a', '#5b8def', '#c084fc', '#d4a017'],
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
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(bootCharts, 50));
  } else {
    window.setTimeout(bootCharts, 50);
  }
})();
