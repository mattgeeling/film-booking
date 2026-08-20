(() => {
  const GOOGLE_CLIENT_ID = '1020593076419-tv5l93390dog560otm0o2p87h36vq1rs.apps.googleusercontent.com';
  const KIT_LABELS = { fuzzy_duck: 'Fuzzy Duck', mark: "Mark's", tom: "Tom's" };
  const CHECKLIST_ITEMS = [
    { key: 'call_sheet', label: 'Call Sheet', required: true },
    { key: 'risk_assessment', label: 'Risk Assessment', required: true },
    { key: 'shot_list', label: 'Shot List', required: true, hasNotRequired: true },
    { key: 'preproduction_creative', label: 'Pre-production creative', required: true },
  ];

  function activeRequiredChecklistItems(booking) {
    return CHECKLIST_ITEMS.filter((item) => item.required && !(item.hasNotRequired && booking['checklist_' + item.key + '_na']));
  }
  const UNAVAILABLE_PERIOD_LABELS = { all_day: '', am: ' (AM)', pm: ' (PM)' };
  const UPCOMING_DAYS_AHEAD = 14;

  const el = {
    authGate: document.getElementById('authGate'),
    appRoot: document.getElementById('appRoot'),
    googleSignInBtn: document.getElementById('googleSignInBtn'),
    authError: document.getElementById('authError'),
    currentUserLabel: document.getElementById('currentUserLabel'),
    signOutBtn: document.getElementById('signOutBtn'),
    dashWelcome: document.getElementById('dashWelcome'),
    statWeek: document.getElementById('statWeek'),
    statNext: document.getElementById('statNext'),
    statKit: document.getElementById('statKit'),
    attentionList: document.getElementById('attentionList'),
    upcomingList: document.getElementById('upcomingList'),
  };

  async function apiGet(path) {
    const res = await fetch(path, { credentials: 'include' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  async function apiPost(path, body) {
    const res = await fetch(path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      body: JSON.stringify(body || {}),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function mondayOfIso(dateIso) {
    const d = new Date(dateIso + 'T00:00:00');
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    return d;
  }

  function recurringRuleMatchesDate(rule, dateIso) {
    const interval = rule.interval_weeks || 1;
    if (interval <= 1) return true;
    if (!rule.anchor_date) return true;
    const weeksBetween = Math.round((mondayOfIso(dateIso) - mondayOfIso(rule.anchor_date)) / (7 * 86400000));
    return ((weeksBetween % interval) + interval) % interval === 0;
  }

  function isoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function formatDateShort(date) {
    return date.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });
  }

  function renderPanelList(listEl, items, emptyText) {
    listEl.innerHTML = '';
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'dash-panel-empty';
      empty.textContent = emptyText;
      listEl.appendChild(empty);
      return;
    }
    for (const item of items) {
      const row = document.createElement('div');
      row.className = 'dash-panel-item';
      const title = document.createElement('span');
      title.className = 'dash-panel-item-title';
      title.textContent = item.title;
      const meta = document.createElement('span');
      meta.className = 'dash-panel-item-meta';
      meta.textContent = item.meta;
      row.appendChild(title);
      row.appendChild(meta);
      listEl.appendChild(row);
    }
  }

  function buildAttentionItems(bookings) {
    const items = [];
    for (const b of bookings) {
      const issues = [];
      if (b.status === 'pencil') issues.push('Awaiting confirmation');
      const missing = activeRequiredChecklistItems(b).filter((item) => !b['checklist_' + item.key]).map((item) => item.label);
      if (missing.length) issues.push(`Missing: ${missing.join(', ')}`);
      if (issues.length) {
        const start = new Date(b.start_datetime.replace(' ', 'T'));
        items.push({ title: b.title, meta: `${formatDateShort(start)} — ${issues.join(' · ')}`, sortKey: b.start_datetime });
      }
    }
    items.sort((a, b) => a.sortKey.localeCompare(b.sortKey));
    return items;
  }

  async function loadAttentionPanel(weekBookings) {
    try {
      renderPanelList(el.attentionList, buildAttentionItems(weekBookings), 'All caught up for this week.');
    } catch (err) {
      renderPanelList(el.attentionList, [], 'Unavailable.');
    }
  }

  async function loadUpcomingPanel() {
    try {
      const [blocked, unavailable, recurring] = await Promise.all([
        apiGet('api/blocked_days_list.php'),
        apiGet('api/person_unavailable_list.php'),
        apiGet('api/person_recurring_unavailable_list.php'),
      ]);

      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const windowEnd = new Date(today);
      windowEnd.setDate(windowEnd.getDate() + UPCOMING_DAYS_AHEAD);
      const todayIso = isoDate(today);
      const windowEndIso = isoDate(windowEnd);
      const inWindow = (day) => day >= todayIso && day <= windowEndIso;

      const items = [];
      for (const b of blocked) {
        if (!inWindow(b.day)) continue;
        const d = new Date(b.day + 'T00:00:00');
        items.push({ title: 'Day blocked', meta: `${formatDateShort(d)}${b.reason ? ' — ' + b.reason : ''}`, sortKey: b.day + ' 0' });
      }
      for (const u of unavailable) {
        if (!inWindow(u.day)) continue;
        const d = new Date(u.day + 'T00:00:00');
        const period = UNAVAILABLE_PERIOD_LABELS[u.period] || '';
        items.push({
          title: `${u.person_name} unavailable${period}`,
          meta: `${formatDateShort(d)}${u.reason ? ' — ' + u.reason : ''}`,
          sortKey: u.day + ' 1',
        });
      }
      if (recurring.length) {
        for (let d = new Date(today); d <= windowEnd; d.setDate(d.getDate() + 1)) {
          const weekday = d.getDay() === 0 ? 7 : d.getDay();
          const dayIso = isoDate(d);
          for (const r of recurring.filter((rule) => rule.weekday === weekday && recurringRuleMatchesDate(rule, dayIso))) {
            const period = UNAVAILABLE_PERIOD_LABELS[r.period] || '';
            items.push({
              title: `${r.person_name} unavailable${period}`,
              meta: `${formatDateShort(d)}${r.reason ? ' — ' + r.reason : ''} (recurring)`,
              sortKey: dayIso + ' 1',
            });
          }
        }
      }
      items.sort((a, b) => a.sortKey.localeCompare(b.sortKey));

      renderPanelList(el.upcomingList, items, 'Nothing blocked in the next two weeks.');
    } catch (err) {
      renderPanelList(el.upcomingList, [], 'Unavailable.');
    }
  }

  async function loadDashboardStats() {
    let weekBookings = [];
    try {
      const week = await apiGet('api/bookings_list.php');
      weekBookings = week.bookings;
      const total = week.bookings.length;
      const confirmed = week.bookings.filter((b) => b.status === 'confirmed').length;
      const pencil = total - confirmed;
      el.statWeek.textContent = total === 0
        ? 'Nothing booked this week'
        : `${total} booking${total === 1 ? '' : 's'} · ${pencil} pencil · ${confirmed} confirmed`;

      const now = new Date();
      const upcoming = week.bookings
        .filter((b) => new Date(b.end_datetime.replace(' ', 'T')) >= now)
        .sort((a, b) => a.start_datetime.localeCompare(b.start_datetime));
      if (upcoming.length) {
        const b = upcoming[0];
        const start = new Date(b.start_datetime.replace(' ', 'T'));
        el.statNext.textContent = `${b.title} — ${formatDateShort(start)}, ${formatTime(start)}`;
      } else {
        el.statNext.textContent = 'Nothing upcoming this week';
      }
    } catch (err) {
      el.statWeek.textContent = 'Unavailable';
      el.statNext.textContent = 'Unavailable';
    }

    try {
      const kit = await apiGet('api/kit_usage.php');
      const parts = Object.keys(KIT_LABELS)
        .map((key) => `${KIT_LABELS[key]} ${kit.counts[key] || 0}d`)
        .join(' · ');
      el.statKit.textContent = parts;
    } catch (err) {
      el.statKit.textContent = 'Unavailable';
    }

    await loadAttentionPanel(weekBookings);
    await loadUpcomingPanel();
  }

  function waitForGoogleIdentity(cb) {
    if (window.google && window.google.accounts && window.google.accounts.id) {
      cb();
    } else {
      setTimeout(() => waitForGoogleIdentity(cb), 50);
    }
  }

  function initSignIn() {
    waitForGoogleIdentity(() => {
      google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: onCredentialResponse,
      });
      google.accounts.id.renderButton(el.googleSignInBtn, { theme: 'outline', size: 'large' });
    });
  }

  async function onCredentialResponse(response) {
    el.authError.classList.add('hidden');
    try {
      const user = await apiPost('api/auth_login.php', { id_token: response.credential });
      await showApp(user);
    } catch (err) {
      el.authError.textContent = err.message;
      el.authError.classList.remove('hidden');
    }
  }

  async function showApp(user) {
    el.authGate.classList.add('hidden');
    el.appRoot.classList.remove('hidden');
    el.currentUserLabel.textContent = user.name || user.email;
    el.dashWelcome.textContent = `Welcome back, ${(user.name || user.email).split(' ')[0]}.`;
    await loadDashboardStats();
  }

  function showAuthGate() {
    el.appRoot.classList.add('hidden');
    el.authGate.classList.remove('hidden');
    initSignIn();
  }

  el.signOutBtn.addEventListener('click', async () => {
    await apiPost('api/auth_logout.php');
    showAuthGate();
  });

  (async function init() {
    try {
      const user = await apiGet('api/auth_me.php');
      await showApp(user);
    } catch (err) {
      showAuthGate();
    }
  })();
})();
