/**
 * Dev Pulse interactive dashboard.
 *
 * Ported from verygoodplugins/activity-report (index.html). The standalone
 * deploy fetches activity-data.json from the same origin; here, WordPress
 * inlines a slim payload as <script id="dev-pulse-data" type="application/json">
 * which we parse on DOMContentLoaded — no client-side network call.
 *
 * Bails silently if the payload tag isn't present (so this script is a no-op
 * if it's accidentally enqueued on another template).
 */
(function () {
  'use strict';

  const payloadEl = document.getElementById('dev-pulse-data');
  if (!payloadEl) return;

  let SITE_CONFIG = {
    title: 'Dev Pulse',
    tagline: 'Engineering velocity',
    accentColor: null,
    logo: null
  };

  let rawData = null;
  let currentPeriod = 'week';
  let currentView = 'week';
  let viewTransitioning = false;
  let selectedDate = null;

  const PERIOD_LABELS = {
    'day': 'last 24 hours',
    'week': 'this week',
    'month': 'this month'
  };

  const PERIOD_CONFIG = {
    'day':   { hours: 24,  get label() { return SITE_CONFIG.tagline + ' ' + PERIOD_LABELS.day; } },
    'week':  { hours: 168, get label() { return SITE_CONFIG.tagline + ' ' + PERIOD_LABELS.week; } },
    'month': { hours: 720, get label() { return SITE_CONFIG.tagline + ' ' + PERIOD_LABELS.month; } }
  };

  function getRepoLabel(repoName) {
    return repoName;
  }

  function applyPeriodLabel() {
    const labelEl = document.getElementById('period-label');
    if (labelEl) labelEl.textContent = PERIOD_CONFIG[currentPeriod].label;
  }

  let CATEGORY_CONFIG = null;

  function hashColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 55%, 65%)`;
  }

  const REPO_COLORS = new Proxy({}, {
    get: (target, prop) => target[prop] || hashColor(String(prop))
  });

  function formatNumber(num) {
    if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
    return num.toString();
  }

  function escapeHtml(text) {
    return String(text ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatTimestamp(value) {
    if (!value) return 'n/a';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
  }

  function formatDiagnosticList(items) {
    if (!items || items.length === 0) return 'None';
    return items.map(item => `<code>${escapeHtml(item)}</code>`).join('<br>');
  }

  function renderDiagnostics() {
    const panel = document.getElementById('diagnostics-panel');
    const content = document.getElementById('diagnostics-content');
    const diagnostics = rawData?.diagnostics;

    if (!panel || !content) return;

    if (!rawData?.generatedAt && !rawData?.periodStart && !diagnostics) {
      panel.hidden = true;
      return;
    }

    const scan = diagnostics?.scan || {};
    const prs = diagnostics?.prs || {};
    const warnings = [];

    if ((scan.missingRoots || []).length > 0) {
      warnings.push(`Missing scan roots: ${scan.missingRoots.join(', ')}`);
    }
    if ((prs.possiblyTruncatedRepos || []).length > 0) {
      warnings.push(`PR search hit the per-repo limit for: ${prs.possiblyTruncatedRepos.join(', ')}`);
    }
    if ((prs.searchFailures || 0) > 0) {
      warnings.push(`PR search failed for ${prs.searchFailures} repo query${prs.searchFailures === 1 ? '' : 'ies'}.`);
    }
    if ((prs.detailFailures || 0) > 0) {
      warnings.push(`PR detail fetch failed for ${prs.detailFailures} result${prs.detailFailures === 1 ? '' : 's'}.`);
    }

    content.innerHTML = `
      <div class="diagnostics-grid">
        <div class="diagnostics-item">
          <span class="diagnostics-label">Generated</span>
          <div class="diagnostics-value">${escapeHtml(formatTimestamp(rawData.generatedAt))}</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">Period Start</span>
          <div class="diagnostics-value">${escapeHtml(formatTimestamp(rawData.periodStart))}</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">Author Filter</span>
          <div class="diagnostics-value"><code>${escapeHtml(diagnostics?.authorEmail || 'none')}</code></div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">GitHub PR Author</span>
          <div class="diagnostics-value"><code>${escapeHtml(diagnostics?.ghAuthor || 'disabled')}</code></div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">Configured Scan Roots</span>
          <div class="diagnostics-value">${formatDiagnosticList(scan.configuredRoots)}</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">Resolved Scan Roots</span>
          <div class="diagnostics-value">${Array.isArray(scan.resolvedRoots) ? (scan.resolvedRoots.length + ' roots') : 'none'}</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">Repo Discovery</span>
          <div class="diagnostics-value">${scan.discoveredRepos || 0} discovered, ${scan.reposWithCommits || 0} with commits, ${scan.excludedRepos || 0} excluded</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">GitHub Remotes</span>
          <div class="diagnostics-value">${scan.githubReposWithCommits || 0} repos eligible for PR lookups</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">PR Collection</span>
          <div class="diagnostics-value">${prs.dedupedPullRequests || 0} candidates, ${rawData.prs?.length || 0} hydrated, ${prs.repoQueries || 0} repo queries</div>
        </div>
        <div class="diagnostics-item">
          <span class="diagnostics-label">PR Per-Repo Limit</span>
          <div class="diagnostics-value">${prs.enabled ? prs.perRepoLimit || 0 : 'Disabled'}</div>
        </div>
      </div>
      ${warnings.length ? `
        <div class="diagnostics-warnings">
          ${warnings.map(warning => `<div class="diagnostics-warning">${escapeHtml(warning)}</div>`).join('')}
        </div>
      ` : ''}
    `;

    panel.hidden = false;
  }

  function renderMarkdown(text) {
    if (!text) return '';
    let html = escapeHtml(text);

    html = html.replace(/^### (.+)$/gm, '<strong style="font-size: 0.85em;">$1</strong>');
    html = html.replace(/^## (.+)$/gm, '<strong style="font-size: 0.9em;">$1</strong>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/`([^`]+)`/g, '<code style="background: var(--surface-elevated); padding: 0.1em 0.3em; border-radius: 3px; font-size: 0.9em;">$1</code>');

    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, text, url) => {
      const sanitizedUrl = url.trim();
      if (!/^https?:\/\//i.test(sanitizedUrl)) return text;
      return `<a href="${sanitizedUrl}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()" style="color: var(--accent);">${text}</a>`;
    });

    html = html.replace(/^- (.+)$/gm, '<span style="display: block; padding-left: 1em;">• $1</span>');
    html = html.replace(/\n/g, '<br>');

    return html;
  }

  function animateCounter(element, target, prefix = '', duration = 1500) {
    if (!element) return;
    const start = 0;
    const startTime = performance.now();

    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(start + (target - start) * eased);
      element.textContent = prefix + formatNumber(current);
      if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
  }

  function filterByPeriod(commits, period) {
    const now = new Date();
    const hoursAgo = PERIOD_CONFIG[period].hours;
    const cutoff = new Date(now.getTime() - hoursAgo * 60 * 60 * 1000);
    return commits.filter(c => new Date(c.date) >= cutoff);
  }

  function filterByDate(items, date) {
    const targetDate = date.toDateString();
    return items.filter(item => {
      const itemDate = new Date(item.date).toDateString();
      return itemDate === targetDate;
    });
  }

  function formatDateLabel(date) {
    const today = new Date();
    if (date.toDateString() === today.toDateString()) {
      return "Today's engineering velocity";
    }
    const options = { weekday: 'long', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options) + ' velocity';
  }

  function loadData() {
    try {
      rawData = JSON.parse(payloadEl.textContent);
    } catch (e) {
      console.error('Dev Pulse: failed to parse embedded payload', e);
      return;
    }

    CATEGORY_CONFIG = null;
    if (rawData.categories && Object.keys(rawData.categories).length > 0) {
      CATEGORY_CONFIG = rawData.categories;
    }

    applyPeriodLabel();
    renderDiagnostics();
    renderDashboard();
    setupToggleButtons();
  }

  function setupToggleButtons() {
    document.querySelectorAll('.dev-pulse-app .toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const newPeriod = btn.dataset.period;
        if (!newPeriod || viewTransitioning) return;

        document.querySelectorAll('.dev-pulse-app .toggle-btn[data-period]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentPeriod = newPeriod;
        selectedDate = null;
        applyPeriodLabel();

        const newView = newPeriod === 'month' ? 'month' : 'week';
        if (newView === currentView) {
          renderDashboard();
        } else {
          transitionToView(newView);
        }
      });
    });
  }

  function transitionToView(newView, targetDate = null) {
    if (viewTransitioning || (newView === currentView && !targetDate)) return;
    viewTransitioning = true;

    const grid = document.getElementById('weekly-grid');
    const dayColumns = grid.querySelectorAll('.day-column, .day-hero');

    dayColumns.forEach(el => el.classList.add('fade-out'));

    setTimeout(() => {
      grid.classList.remove('view-week', 'view-month', 'view-day');
      grid.classList.add(`view-${newView}`);
      currentView = newView;

      const titleText = document.getElementById('rhythm-title-text');
      const backBtn = document.getElementById('back-to-week-btn');

      if (newView === 'month') {
        if (titleText) titleText.textContent = 'Monthly Calendar';
        if (backBtn) backBtn.style.display = 'inline-block';
      } else if (newView === 'day') {
        if (titleText) titleText.textContent = 'Day Detail';
        if (backBtn) backBtn.style.display = 'inline-block';
      } else {
        if (titleText) titleText.textContent = 'Weekly Rhythm';
        if (backBtn) backBtn.style.display = 'none';
      }

      if (newView === 'month') {
        renderMonthView(rawData.commits, true);
        setTimeout(() => {
          viewTransitioning = false;
          renderDashboard();
        }, 1800);
        return;
      } else if (newView === 'day' && targetDate) {
        selectedDate = targetDate;
        renderDayView(targetDate, rawData.commits);
      } else {
        selectedDate = null;
        renderWeeklyRhythm(rawData.commits);
      }

      const newElements = grid.querySelectorAll('.day-column, .day-hero');
      newElements.forEach((el, i) => {
        el.classList.add('fade-in');
        setTimeout(() => el.classList.remove('fade-in'), 300);
      });

      viewTransitioning = false;
      renderDashboard();
    }, 200);
  }

  function backToWeekView() {
    document.querySelectorAll('.dev-pulse-app .toggle-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.period === 'week');
    });
    currentPeriod = 'week';
    applyPeriodLabel();
    transitionToView('week');
  }

  function selectDay(date) {
    selectedDate = date;
    document.querySelectorAll('.dev-pulse-app .toggle-btn').forEach(b => b.classList.remove('active'));
    const labelEl = document.getElementById('period-label');
    if (labelEl) labelEl.textContent = formatDateLabel(date);
    transitionToView('day', date);
  }

  function renderDashboard() {
    const allCommits = rawData.commits || [];
    const allPrs = rawData.prs || [];

    let commits, prs;

    if (selectedDate) {
      commits = filterByDate(allCommits, selectedDate);
      prs = filterByDate(allPrs, selectedDate);
    } else {
      commits = filterByPeriod(allCommits, currentPeriod);
      prs = filterByPeriod(allPrs, currentPeriod);
    }

    let totalAdded = 0;
    let totalDeleted = 0;
    const activeRepos = new Set();

    commits.forEach(c => {
      if (c.stats) {
        totalAdded += c.stats.added || 0;
        totalDeleted += c.stats.deleted || 0;
      }
      activeRepos.add(c.repo);
    });

    animateCounter(document.getElementById('repos-count'), activeRepos.size);
    animateCounter(document.getElementById('commits-count'), commits.length);
    animateCounter(document.getElementById('added-count'), totalAdded, '+');
    animateCounter(document.getElementById('deleted-count'), totalDeleted, '-');
    animateCounter(document.getElementById('prs-count'), prs.length);

    if (!viewTransitioning) {
      if (currentView === 'week') {
        renderWeeklyRhythm(allCommits);
      } else if (currentView === 'month') {
        renderMonthView(allCommits, false);
      } else if (currentView === 'day' && selectedDate) {
        renderDayView(selectedDate, allCommits);
      }
    }

    renderCategories(commits);
    renderPullRequests(prs);
    renderTimeline(commits);
  }

  function renderWeeklyRhythm(commits) {
    const container = document.getElementById('weekly-grid');
    if (!container) return;
    const days = [];
    const today = new Date();

    for (let i = 6; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      days.push(date);
    }

    const commitsByDay = {};
    commits.forEach(c => {
      const date = new Date(c.date);
      const key = date.toDateString();
      if (!commitsByDay[key]) {
        commitsByDay[key] = { commits: 0, added: 0, deleted: 0, repos: {} };
      }
      commitsByDay[key].commits++;
      commitsByDay[key].added += c.stats?.added || 0;
      commitsByDay[key].deleted += c.stats?.deleted || 0;

      const repo = c.repo;
      if (!commitsByDay[key].repos[repo]) commitsByDay[key].repos[repo] = 0;
      commitsByDay[key].repos[repo]++;
    });

    container.innerHTML = days.map((date, index) => {
      const key = date.toDateString();
      const dayData = commitsByDay[key] || { commits: 0, added: 0, deleted: 0, repos: {} };
      const isToday = date.toDateString() === today.toDateString();
      const isSelected = selectedDate && date.toDateString() === selectedDate.toDateString();
      const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

      const repoEntries = Object.entries(dayData.repos);
      const bars = repoEntries.slice(0, 4).map(([repo, count]) => {
        const color = REPO_COLORS[repo] || '#B8C4D0';
        const width = Math.max((count / Math.max(...Object.values(dayData.repos))) * 100, 10);
        return `<div class="activity-bar"><div class="activity-fill" style="background: ${color}; width: ${width}%;"></div></div>`;
      }).join('');

      const classes = ['day-column'];
      if (isToday) classes.push('today');
      if (isSelected) classes.push('selected');

      return `
        <div class="${classes.join(' ')}" data-date-index="${index}" onclick="handleDayClick(${index})">
          <div class="day-name">${dayNames[date.getDay()]}</div>
          <div class="day-date">${date.getDate()}</div>
          <div class="day-bars">${bars || '<div style="color: var(--text-secondary); font-size: 0.75rem; opacity: 0.5;">No activity</div>'}</div>
          <div class="day-stats">
            <span class="day-stat added">+${formatNumber(dayData.added)}</span>
            <span class="day-stat deleted">-${formatNumber(dayData.deleted)}</span>
          </div>
        </div>
      `;
    }).join('');
  }

  function handleDayClick(index) {
    const today = new Date();
    const days = [];
    for (let i = 6; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      days.push(date);
    }
    transitionToView('day', days[index]);
  }

  function handleMonthDayClick(dateStr) {
    const date = new Date(dateStr);
    transitionToView('day', date);
  }

  function renderMonthView(commits, applyStagger = false) {
    const container = document.getElementById('weekly-grid');
    if (!container) return;
    const today = new Date();
    const days = [];

    for (let i = 34; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      days.push(date);
    }

    const commitsByDay = {};
    commits.forEach(c => {
      const date = new Date(c.date);
      const key = date.toDateString();
      if (!commitsByDay[key]) {
        commitsByDay[key] = { commits: 0, added: 0, deleted: 0, repos: {} };
      }
      commitsByDay[key].commits++;
      commitsByDay[key].added += c.stats?.added || 0;
      commitsByDay[key].deleted += c.stats?.deleted || 0;

      const repo = c.repo;
      if (!commitsByDay[key].repos[repo]) commitsByDay[key].repos[repo] = 0;
      commitsByDay[key].repos[repo]++;
    });

    const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    container.innerHTML = days.map((date, index) => {
      const key = date.toDateString();
      const dayData = commitsByDay[key] || { commits: 0, added: 0, deleted: 0, repos: {} };
      const isToday = date.toDateString() === today.toDateString();
      const isSelected = selectedDate && date.toDateString() === selectedDate.toDateString();
      const weekRow = Math.floor(index / 7);

      const repoEntries = Object.entries(dayData.repos);
      const maxRepoCommits = Math.max(...Object.values(dayData.repos), 1);
      const bars = repoEntries
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3)
        .map(([repo, count]) => {
          const color = REPO_COLORS[repo] || '#B8C4D0';
          const width = Math.max((count / maxRepoCommits) * 100, 15);
          return `<div class="activity-bar"><div class="activity-fill" style="background: ${color}; width: ${width}%;"></div></div>`;
        }).join('');

      const classes = ['day-column'];
      if (isToday) classes.push('today');
      if (isSelected) classes.push('selected');
      if (applyStagger) classes.push('week-stagger');

      return `
        <div class="${classes.join(' ')}" data-week="${weekRow}" onclick="handleMonthDayClick('${date.toISOString()}')">
          <div class="day-name">${dayNames[date.getDay()]}</div>
          <div class="day-date">${date.getDate()}</div>
          <div class="day-bars">
            ${bars || '<div style="opacity: 0.3; font-size: 0.6rem; color: var(--text-secondary)">—</div>'}
          </div>
          <div class="day-stats">
            <span class="day-stat added">+${formatNumber(dayData.added)}</span>
            <span class="day-stat deleted">-${formatNumber(dayData.deleted)}</span>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderDayView(date, commits) {
    const container = document.getElementById('weekly-grid');
    if (!container) return;
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const dayCommits = commits.filter(c => {
      const commitDate = new Date(c.date);
      return commitDate.toDateString() === date.toDateString();
    });

    let totalAdded = 0, totalDeleted = 0;
    const repoStats = {};
    const hourlyActivity = new Array(24).fill(0);

    dayCommits.forEach(c => {
      totalAdded += c.stats?.added || 0;
      totalDeleted += c.stats?.deleted || 0;

      if (!repoStats[c.repo]) {
        repoStats[c.repo] = { commits: 0, url: c.remoteUrl };
      }
      repoStats[c.repo].commits++;

      const hour = new Date(c.date).getHours();
      hourlyActivity[hour]++;
    });

    const maxHourly = Math.max(...hourlyActivity, 1);

    const hourlyBars = hourlyActivity.map((count, hour) => {
      const height = count > 0 ? Math.max((count / maxHourly) * 100, 10) : 4;
      const tooltip = `${hour}:00 - ${count} commit${count !== 1 ? 's' : ''}`;
      return `<div class="hour-bar" style="height: ${height}%" data-tooltip="${tooltip}"></div>`;
    }).join('');

    const repoChips = Object.entries(repoStats)
      .sort((a, b) => b[1].commits - a[1].commits)
      .slice(0, 8)
      .map(([repo, data]) => {
        const color = REPO_COLORS[repo] || '#6366f1';
        return `
          <a href="${data.url}" target="_blank" class="day-repo-chip">
            <span class="repo-dot" style="background: ${color}"></span>
            ${repo}
            <span style="color: var(--text-secondary)">${data.commits}</span>
          </a>
        `;
      }).join('');

    const isToday = date.toDateString() === new Date().toDateString();
    const dateLabel = isToday ? 'Today' : `${monthNames[date.getMonth()]} ${date.getDate()}`;

    container.innerHTML = `
      <div class="day-hero">
        <div class="hero-weekday">${dayNames[date.getDay()]}</div>
        <div class="hero-date">${dateLabel}</div>
        <div class="hero-stats-row">
          <div class="hero-stat">
            <div class="hero-stat-value">${dayCommits.length}</div>
            <div class="hero-stat-label">Commits</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-value added">+${formatNumber(totalAdded)}</div>
            <div class="hero-stat-label">Lines Added</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-value deleted">-${formatNumber(totalDeleted)}</div>
            <div class="hero-stat-label">Lines Removed</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-value">${Object.keys(repoStats).length}</div>
            <div class="hero-stat-label">Repos</div>
          </div>
        </div>
        ${dayCommits.length > 0 ? `
        <div class="hourly-chart">
          <div class="hourly-chart-title">Activity by Hour</div>
          <div class="hourly-bars">${hourlyBars}</div>
          <div class="hour-labels">
            <span>12am</span>
            <span>6am</span>
            <span>12pm</span>
            <span>6pm</span>
            <span>11pm</span>
          </div>
        </div>
        <div class="day-repos">
          <div class="day-repos-title">Repos Touched</div>
          <div class="day-repos-grid">${repoChips || '<span style="color: var(--text-secondary)">No repos</span>'}</div>
        </div>
        ` : '<p style="color: var(--text-secondary); margin-top: 1rem;">No activity on this day</p>'}
      </div>
    `;
  }

  function renderCategories(commits) {
    const container = document.getElementById('categories-grid');
    if (!container) return;

    const repoStats = {};
    commits.forEach(c => {
      const repo = c.repo;
      if (!repoStats[repo]) {
        repoStats[repo] = { commits: 0, added: 0, deleted: 0, url: c.remoteUrl };
      }
      repoStats[repo].commits++;
      repoStats[repo].added += c.stats?.added || 0;
      repoStats[repo].deleted += c.stats?.deleted || 0;
    });

    if (!CATEGORY_CONFIG || Object.keys(CATEGORY_CONFIG).length === 0) {
      const repoItems = Object.entries(repoStats)
        .sort((a, b) => b[1].commits - a[1].commits)
        .map(([name, stats]) => `
          <a href="${stats.url}" target="_blank" class="repo-item">
            <span class="repo-dot" style="background: ${REPO_COLORS[name] || '#6366f1'}"></span>
            <span class="repo-name">${name}</span>
            <div class="repo-stats">
              <span class="repo-commits">${stats.commits}c</span>
              <span class="repo-added">+${formatNumber(stats.added)}</span>
              <span class="repo-deleted">-${formatNumber(stats.deleted)}</span>
            </div>
          </a>
        `).join('');
      container.innerHTML = repoItems
        ? `<div class="category-card"><div class="repo-list">${repoItems}</div></div>`
        : '';
      return;
    }

    const categorized = {};

    Object.entries(CATEGORY_CONFIG).forEach(([category, config]) => {
      categorized[category] = { ...config, repos: [] };
    });

    categorized['Other'] = { icon: '📁', class: 'other', repos: [] };

    Object.entries(repoStats).forEach(([repo, stats]) => {
      let found = false;
      for (const [category, config] of Object.entries(CATEGORY_CONFIG)) {
        if (config.repos.includes(repo)) {
          categorized[category].repos.push({ name: repo, ...stats });
          found = true;
          break;
        }
      }
      if (!found) categorized['Other'].repos.push({ name: repo, ...stats });
    });

    container.innerHTML = Object.entries(categorized)
      .filter(([_, cat]) => cat.repos.length > 0)
      .map(([name, cat]) => {
        const totalCommits = cat.repos.reduce((sum, r) => sum + r.commits, 0);
        const repoItems = cat.repos
          .sort((a, b) => b.commits - a.commits)
          .map(repo => `
            <a href="${repo.url}" target="_blank" class="repo-item">
              <span class="repo-dot" style="background: ${REPO_COLORS[repo.name] || '#6366f1'}"></span>
              <span class="repo-name">${repo.name}</span>
              <div class="repo-stats">
                <span class="repo-commits">${repo.commits}c</span>
                <span class="repo-added">+${formatNumber(repo.added)}</span>
                <span class="repo-deleted">-${formatNumber(repo.deleted)}</span>
              </div>
            </a>
          `).join('');

        return `
          <div class="category-card">
            <div class="category-header">
              <span class="category-icon ${cat.class}">${cat.icon}</span>
              <span class="category-name">${name}</span>
              <span class="category-count">${totalCommits} commits</span>
            </div>
            <div class="repo-list">${repoItems}</div>
          </div>
        `;
      }).join('');
  }

  function renderPullRequests(prs) {
    const container = document.getElementById('pr-grid');
    if (!container) return;

    // Editorial pass: keep title, repo, state, +/- only. The full body/file
     // detail lives on GitHub — link out instead of expanding inline.
    container.innerHTML = prs.slice(0, 8).map(pr => {
      const state = pr.state === 'MERGED' ? 'merged' : (pr.state === 'CLOSED' ? 'closed' : 'open');
      const stateIcon = state === 'merged' ? '🔀' : (state === 'open' ? '🟢' : '🔴');

      return `
        <div class="pr-card">
          <div class="pr-card-main">
            <div class="pr-status ${state}">${stateIcon}</div>
            <div class="pr-info">
              <a href="${pr.url}" target="_blank" class="pr-title-text">${escapeHtml(pr.title)}</a>
              <div class="pr-meta">
                <span class="pr-repo">${getRepoLabel(pr.repo)}</span>
                <span class="pr-state-label">${state}</span>
              </div>
            </div>
            <div class="pr-stat-box">
              <div class="added">+${formatNumber(pr.stats?.added || 0)}</div>
              <div class="deleted">-${formatNumber(pr.stats?.deleted || 0)}</div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderTimeline(commits) {
    const container = document.getElementById('timeline');
    if (!container) return;

    const byDay = {};
    commits.forEach(c => {
      const date = new Date(c.date);
      const key = date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
      if (!byDay[key]) byDay[key] = [];
      byDay[key].push(c);
    });

    const activeRepos = [...new Set(commits.map(c => c.repo))];
    const repoCommitCounts = {};
    commits.forEach(c => { repoCommitCounts[c.repo] = (repoCommitCounts[c.repo] || 0) + 1; });
    activeRepos.sort((a, b) => repoCommitCounts[b] - repoCommitCounts[a]);

    const laneAssignments = {};
    activeRepos.slice(0, 6).forEach((repo, i) => { laneAssignments[repo] = i; });
    const LANE_WIDTH = 12;
    const LANE_GAP = 2;
    const GRAPH_PADDING = 10;

    container.innerHTML = Object.entries(byDay).map(([day, dayCommits]) => {
      const lastCommitY = {};
      const ROW_HEIGHT = 80;

      let svgPaths = '';
      let svgDots = '';
      const limitedCommits = dayCommits.slice(0, 15);

      limitedCommits.forEach((c, idx) => {
        const lane = laneAssignments[c.repo];
        if (lane === undefined) return;

        const color = REPO_COLORS[c.repo] || '#6366f1';
        const x = GRAPH_PADDING + lane * (LANE_WIDTH + LANE_GAP) + LANE_WIDTH / 2;
        const y = idx * ROW_HEIGHT + ROW_HEIGHT / 2;

        if (lastCommitY[c.repo] !== undefined) {
          const prevY = lastCommitY[c.repo];
          const midY = (prevY + y) / 2;
          svgPaths += `<path class="graph-path" d="M${x},${prevY} C${x},${midY} ${x},${midY} ${x},${y}" stroke="${color}" opacity="0.7"/>`;
        }

        svgDots += `<circle cx="${x}" cy="${y}" r="5" fill="${color}" stroke="var(--bg)" stroke-width="2"/>`;
        lastCommitY[c.repo] = y;
      });

      const svgHeight = limitedCommits.length * ROW_HEIGHT;

      let laneLines = '';
      Object.entries(laneAssignments).forEach(([repo, lane]) => {
        const color = REPO_COLORS[repo] || '#6366f1';
        const x = GRAPH_PADDING + lane * (LANE_WIDTH + LANE_GAP) + LANE_WIDTH / 2;
        laneLines += `<line x1="${x}" y1="0" x2="${x}" y2="${svgHeight}" stroke="${color}" stroke-width="2" opacity="0.15"/>`;
      });

      // Editorial pass: headline + hash + repo + +/-. Body/file detail lives
      // on GitHub — the headline is a permalink, no inline expansion.
      const commitItems = limitedCommits.map((c, idx) => {
        const time = new Date(c.date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const color = REPO_COLORS[c.repo] || '#6366f1';
        const commitUrl = c.owner && c.repo && c.hash
          ? `https://github.com/${c.owner}/${c.repo}/commit/${c.hash}`
          : (c.remoteUrl || '#');

        return `
          <div class="commit-item" data-repo="${c.repo}" data-idx="${idx}">
            <div class="commit-time">${time}</div>
            <div class="commit-graph-cell"></div>
            <div class="commit-content">
              <div class="commit-card">
                <div class="commit-header">
                  <span class="commit-repo" style="color: ${color}">${getRepoLabel(c.repo)}</span>
                  <div class="commit-stats">
                    <span class="added">+${c.stats?.added || 0}</span>
                    <span class="deleted">-${c.stats?.deleted || 0}</span>
                  </div>
                </div>
                <div class="commit-message">
                  <a href="${commitUrl}" target="_blank">${escapeHtml(c.headline)}</a>
                  <a href="${commitUrl}" target="_blank" class="commit-hash">${c.shortHash}</a>
                </div>
              </div>
            </div>
          </div>
        `;
      }).join('');

      return `
        <div class="timeline-day">
          <div class="timeline-day-header">${day}</div>
          <div class="timeline-commits-wrapper" style="position: relative;">
            <svg class="timeline-graph-svg" width="80" height="${svgHeight}" style="position: absolute; left: 80px; top: 0; pointer-events: none; z-index: 1;">
              ${laneLines}
              ${svgPaths}
              ${svgDots}
            </svg>
            ${commitItems}
          </div>
        </div>
      `;
    }).join('');
  }

  // Inline onclick attributes in rendered markup reference these as globals.
  window.handleDayClick = handleDayClick;
  window.handleMonthDayClick = handleMonthDayClick;
  window.backToWeekView = backToWeekView;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadData);
  } else {
    loadData();
  }
})();
