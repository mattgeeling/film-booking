(() => {
  const GRID_START_HOUR = 7;
  const GRID_END_HOUR = 19;
  const PX_PER_HOUR = 60;
  const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
  const STATUS_LABELS = { pencil: '✎ Pencil', confirmed: '✓ Confirmed' };
  const UNAVAILABLE_PERIOD_LABELS = { all_day: 'All day', am: 'AM', pm: 'PM' };
  const WEEKDAY_LABELS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const CHECKLIST_ITEMS = [
    { key: 'call_sheet', elKey: 'CallSheet', label: 'Call Sheet', required: true },
    { key: 'risk_assessment', elKey: 'RiskAssessment', label: 'Risk Assessment', required: true },
    { key: 'shot_list', elKey: 'ShotList', label: 'Shot List', required: true, hasNotRequired: true },
    { key: 'preproduction_creative', elKey: 'PreproductionCreative', label: 'Pre-production creative', required: true },
    { key: 'additional_documents', elKey: 'AdditionalDocuments', label: 'Additional documents', required: false },
  ];

  function activeRequiredChecklistItems(booking) {
    return CHECKLIST_ITEMS.filter((item) => item.required && !(item.hasNotRequired && booking['checklist_' + item.key + '_na']));
  }
  const GOOGLE_CLIENT_ID = '1020593076419-tv5l93390dog560otm0o2p87h36vq1rs.apps.googleusercontent.com';

  const state = {
    weekStart: mondayOf(new Date()),
    bookings: [],
    people: [],
    clients: [],
    blockedDaysByDate: {},
    unavailableByDate: {},
    recurringUnavailability: [],
    adminSubView: 'clients',
    kitUsagePageMonth: null,
    currentView: 'week',
    monthStr: null,
    monthBookings: [],
    filters: { personId: '', clientId: '', kit: '' },
    editingId: null,
  };

  const el = {
    weekGrid: document.getElementById('weekGrid'),
    weekBody: document.getElementById('weekBody'),
    weekLabel: document.getElementById('weekLabel'),
    weekTally: document.getElementById('weekTally'),
    peopleSummaryList: document.getElementById('peopleSummaryList'),
    prevWeek: document.getElementById('prevWeek'),
    nextWeek: document.getElementById('nextWeek'),
    todayBtn: document.getElementById('todayBtn'),
    addBookingBtn: document.getElementById('addBookingBtn'),
    downloadWeekBtn: document.getElementById('downloadWeekBtn'),
    printHeader: document.getElementById('printHeader'),
    modalBackdrop: document.getElementById('modalBackdrop'),
    bookingForm: document.getElementById('bookingForm'),
    modalTitle: document.getElementById('modalTitle'),
    fieldTitle: document.getElementById('fieldTitle'),
    fieldDate: document.getElementById('fieldDate'),
    fieldStart: document.getElementById('fieldStart'),
    fieldEnd: document.getElementById('fieldEnd'),
    fieldLocation: document.getElementById('fieldLocation'),
    fieldLocationMapsLink: document.getElementById('fieldLocationMapsLink'),
    fieldWhat3Words: document.getElementById('fieldWhat3Words'),
    fieldWhat3WordsLink: document.getElementById('fieldWhat3WordsLink'),
    fieldClient: document.getElementById('fieldClient'),
    fieldKitSource: document.getElementById('fieldKitSource'),
    fieldAttendees: document.getElementById('fieldAttendees'),
    fieldNotes: document.getElementById('fieldNotes'),
    fieldSkipCalendarSync: document.getElementById('fieldSkipCalendarSync'),
    fieldChecklistCallSheet: document.getElementById('fieldChecklistCallSheet'),
    fieldChecklistRiskAssessment: document.getElementById('fieldChecklistRiskAssessment'),
    fieldChecklistShotList: document.getElementById('fieldChecklistShotList'),
    fieldChecklistShotListNa: document.getElementById('fieldChecklistShotListNa'),
    fieldChecklistPreproductionCreative: document.getElementById('fieldChecklistPreproductionCreative'),
    fieldChecklistAdditionalDocuments: document.getElementById('fieldChecklistAdditionalDocuments'),
    fieldChecklistCallSheetBy: document.getElementById('fieldChecklistCallSheetBy'),
    fieldChecklistRiskAssessmentBy: document.getElementById('fieldChecklistRiskAssessmentBy'),
    fieldChecklistShotListBy: document.getElementById('fieldChecklistShotListBy'),
    fieldChecklistPreproductionCreativeBy: document.getElementById('fieldChecklistPreproductionCreativeBy'),
    fieldChecklistAdditionalDocumentsBy: document.getElementById('fieldChecklistAdditionalDocumentsBy'),
    fieldChecklistCallSheetUrl: document.getElementById('fieldChecklistCallSheetUrl'),
    fieldChecklistRiskAssessmentUrl: document.getElementById('fieldChecklistRiskAssessmentUrl'),
    fieldChecklistShotListUrl: document.getElementById('fieldChecklistShotListUrl'),
    fieldChecklistPreproductionCreativeUrl: document.getElementById('fieldChecklistPreproductionCreativeUrl'),
    fieldChecklistAdditionalDocumentsUrl: document.getElementById('fieldChecklistAdditionalDocumentsUrl'),
    fieldChecklistCallSheetUrlLink: document.getElementById('fieldChecklistCallSheetUrlLink'),
    fieldChecklistRiskAssessmentUrlLink: document.getElementById('fieldChecklistRiskAssessmentUrlLink'),
    fieldChecklistShotListUrlLink: document.getElementById('fieldChecklistShotListUrlLink'),
    fieldChecklistPreproductionCreativeUrlLink: document.getElementById('fieldChecklistPreproductionCreativeUrlLink'),
    fieldChecklistAdditionalDocumentsUrlLink: document.getElementById('fieldChecklistAdditionalDocumentsUrlLink'),
    openAllDocsBtn: document.getElementById('openAllDocsBtn'),
    formError: document.getElementById('formError'),
    cancelModalBtn: document.getElementById('cancelModalBtn'),
    deleteBookingBtn: document.getElementById('deleteBookingBtn'),
    confirmBookingBtn: document.getElementById('confirmBookingBtn'),
    unconfirmBookingBtn: document.getElementById('unconfirmBookingBtn'),
    emailConfirmationBtn: document.getElementById('emailConfirmationBtn'),
    emailPreviewBackdrop: document.getElementById('emailPreviewBackdrop'),
    emailPreviewRecipients: document.getElementById('emailPreviewRecipients'),
    emailPreviewSubject: document.getElementById('emailPreviewSubject'),
    emailPreviewFrame: document.getElementById('emailPreviewFrame'),
    emailPreviewError: document.getElementById('emailPreviewError'),
    cancelEmailPreviewBtn: document.getElementById('cancelEmailPreviewBtn'),
    sendEmailPreviewBtn: document.getElementById('sendEmailPreviewBtn'),
    syncResults: document.getElementById('syncResults'),
    conflictWarning: document.getElementById('conflictWarning'),
    viewWeekBtn: document.getElementById('viewWeekBtn'),
    viewMonthBtn: document.getElementById('viewMonthBtn'),
    monthView: document.getElementById('monthView'),
    prevMonth: document.getElementById('prevMonth'),
    thisMonthBtn: document.getElementById('thisMonthBtn'),
    nextMonth: document.getElementById('nextMonth'),
    monthLabel: document.getElementById('monthLabel'),
    monthGrid: document.getElementById('monthGrid'),
    filterPerson: document.getElementById('filterPerson'),
    filterClient: document.getElementById('filterClient'),
    filterKit: document.getElementById('filterKit'),
    clearFiltersBtn: document.getElementById('clearFiltersBtn'),
    viewPeopleBtn: document.getElementById('viewPeopleBtn'),
    weekControls: document.getElementById('weekControls'),
    weekLegend: document.getElementById('weekLegend'),
    peopleView: document.getElementById('peopleView'),
    personForm: document.getElementById('personForm'),
    personName: document.getElementById('personName'),
    personRole: document.getElementById('personRole'),
    personEmail: document.getElementById('personEmail'),
    personFormError: document.getElementById('personFormError'),
    peopleTableBody: document.getElementById('peopleTableBody'),
    viewAdminBtn: document.getElementById('viewAdminBtn'),
    adminView: document.getElementById('adminView'),
    adminTabClientsBtn: document.getElementById('adminTabClientsBtn'),
    adminTabKitUsageBtn: document.getElementById('adminTabKitUsageBtn'),
    adminTabBlockedDaysBtn: document.getElementById('adminTabBlockedDaysBtn'),
    adminTabNeedsPrepBtn: document.getElementById('adminTabNeedsPrepBtn'),
    clientsView: document.getElementById('clientsView'),
    clientForm: document.getElementById('clientForm'),
    clientName: document.getElementById('clientName'),
    clientLogo: document.getElementById('clientLogo'),
    clientFormError: document.getElementById('clientFormError'),
    clientsTableBody: document.getElementById('clientsTableBody'),
    kitUsagePanel: document.getElementById('kitUsagePanel'),
    kitUsageTitle: document.getElementById('kitUsageTitle'),
    kitUsageList: document.getElementById('kitUsageList'),
    kitUsagePageView: document.getElementById('kitUsagePageView'),
    kitUsagePrevMonth: document.getElementById('kitUsagePrevMonth'),
    kitUsageThisMonth: document.getElementById('kitUsageThisMonth'),
    kitUsageNextMonth: document.getElementById('kitUsageNextMonth'),
    kitUsagePageMonthLabel: document.getElementById('kitUsagePageMonthLabel'),
    kitUsagePageSummary: document.getElementById('kitUsagePageSummary'),
    kitUsagePageTableBody: document.getElementById('kitUsagePageTableBody'),
    blockedDaysView: document.getElementById('blockedDaysView'),
    blockedDayForm: document.getElementById('blockedDayForm'),
    blockedDayDate: document.getElementById('blockedDayDate'),
    blockedDayReason: document.getElementById('blockedDayReason'),
    blockedDayFormError: document.getElementById('blockedDayFormError'),
    blockedDaysTableBody: document.getElementById('blockedDaysTableBody'),
    needsPrepView: document.getElementById('needsPrepView'),
    needsPrepTableBody: document.getElementById('needsPrepTableBody'),
    authGate: document.getElementById('authGate'),
    appRoot: document.getElementById('appRoot'),
    googleSignInBtn: document.getElementById('googleSignInBtn'),
    authError: document.getElementById('authError'),
    currentUserLabel: document.getElementById('currentUserLabel'),
    signOutBtn: document.getElementById('signOutBtn'),
  };

  function mondayOf(date) {
    const d = new Date(date);
    const day = d.getDay(); // 0=Sun..6=Sat
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function isoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function addDays(date, n) {
    const d = new Date(date);
    d.setDate(d.getDate() + n);
    return d;
  }

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

  async function apiPostForm(path, formData) {
    const res = await fetch(path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'fetch' },
      body: formData,
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  function buildWeekSkeleton() {
    el.weekGrid.innerHTML = '';
    const todayIso = isoDate(new Date());

    const gutterHeader = document.createElement('div');
    gutterHeader.className = 'time-gutter-header';
    el.weekGrid.appendChild(gutterHeader);

    for (let i = 0; i < 5; i++) {
      const d = addDays(state.weekStart, i);
      const dIso = isoDate(d);
      const blocked = state.blockedDaysByDate[dIso];
      const isPast = dIso < todayIso;
      const header = document.createElement('div');
      header.className = 'day-header' + (dIso === todayIso ? ' is-today' : '') + (blocked ? ' is-blocked' : '') + (isPast ? ' is-past' : '');
      header.innerHTML = `${DAY_NAMES[i]}<span class="day-date">${d.getDate()}</span>`;

      const lockBtn = document.createElement('button');
      lockBtn.type = 'button';
      lockBtn.className = 'day-lock-btn';
      lockBtn.title = blocked ? `Blocked: ${blocked.reason || 'no reason given'} (click to unblock)` : 'Block this day for bookings';
      lockBtn.textContent = blocked ? '🔒' : '🔓';
      lockBtn.addEventListener('click', (e) => onDayLockClick(dIso, e));
      header.appendChild(lockBtn);
      if (blocked) {
        const reasonEl = document.createElement('span');
        reasonEl.className = 'day-blocked-reason';
        reasonEl.textContent = blocked.reason || 'Blocked';
        header.appendChild(reasonEl);
      }

      const unavailBadge = buildUnavailableBadge(dIso);
      if (unavailBadge) header.appendChild(unavailBadge);

      el.weekGrid.appendChild(header);
    }

    const gutter = document.createElement('div');
    gutter.className = 'time-gutter';
    gutter.style.height = `${(GRID_END_HOUR - GRID_START_HOUR) * PX_PER_HOUR}px`;
    for (let h = GRID_START_HOUR; h <= GRID_END_HOUR; h++) {
      const label = document.createElement('span');
      label.className = 'hour-label';
      label.style.top = `${(h - GRID_START_HOUR) * PX_PER_HOUR}px`;
      label.textContent = `${h}:00`;
      gutter.appendChild(label);
    }
    el.weekGrid.appendChild(gutter);

    for (let i = 0; i < 5; i++) {
      const col = document.createElement('div');
      const colDate = addDays(state.weekStart, i);
      const colIso = isoDate(colDate);
      col.className = 'day-column' + (colIso === todayIso ? ' is-today' : '') + (state.blockedDaysByDate[colIso] ? ' is-blocked' : '') + (colIso < todayIso ? ' is-past' : '');
      col.dataset.dayIndex = String(i);
      col.style.height = `${(GRID_END_HOUR - GRID_START_HOUR) * PX_PER_HOUR}px`;
      col.addEventListener('click', (e) => {
        if (e.target === col) onDayColumnClick(i);
      });
      el.weekGrid.appendChild(col);
    }
  }

  function timeToOffsetPx(dateObj) {
    const hours = dateObj.getHours() + dateObj.getMinutes() / 60;
    const clamped = Math.min(Math.max(hours, GRID_START_HOUR), GRID_END_HOUR);
    return (clamped - GRID_START_HOUR) * PX_PER_HOUR;
  }

  function buildBlockChecklistRow(booking, fieldKey, label, naFieldKey) {
    const row = document.createElement('label');
    row.className = 'b-checklist-item';
    const isNa = naFieldKey ? !!booking[naFieldKey] : false;

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = isNa ? false : !!booking[fieldKey];
    checkbox.disabled = isNa;
    checkbox.addEventListener('click', (e) => e.stopPropagation());
    checkbox.addEventListener('change', async () => {
      checkbox.disabled = true;
      try {
        await apiPost(`api/bookings_update.php?id=${booking.id}`, { [fieldKey]: checkbox.checked });
        await loadWeek();
      } catch (err) {
        checkbox.checked = !checkbox.checked;
        checkbox.disabled = false;
        alert(err.message);
      }
    });

    const labelText = document.createElement('span');
    labelText.className = 'b-checklist-label';
    labelText.textContent = isNa ? `${label} (not required)` : label;

    row.appendChild(checkbox);
    row.appendChild(labelText);

    if (isNa) {
      return row;
    }

    const url = booking[fieldKey + '_url'];
    if (url) {
      const linkEl = document.createElement('a');
      linkEl.className = 'b-checklist-link';
      linkEl.href = url;
      linkEl.target = '_blank';
      linkEl.rel = 'noopener';
      linkEl.title = 'Open in Google Drive';
      linkEl.textContent = '🔗';
      linkEl.addEventListener('click', (e) => e.stopPropagation());
      row.appendChild(linkEl);
    }

    const byName = booking[fieldKey] ? booking[fieldKey + '_by'] : null;
    if (byName) {
      const byEl = document.createElement('span');
      byEl.className = 'b-checklist-by';
      byEl.style.backgroundColor = colorForName(byName);
      byEl.textContent = initialsForName(byName);
      byEl.title = byName;
      row.appendChild(byEl);
    }
    return row;
  }

  function layoutDayBookings(items) {
    items.sort((a, b) => a.startMs - b.startMs || a.endMs - b.endMs);
    let columns = [];
    let cluster = [];
    let clusterEnd = -Infinity;

    const finalizeCluster = () => {
      const numCols = columns.length;
      for (const it of cluster) it.totalCols = numCols;
    };

    for (const it of items) {
      if (it.startMs >= clusterEnd) {
        finalizeCluster();
        columns = [];
        cluster = [];
        clusterEnd = -Infinity;
      }
      let col = columns.findIndex((endMs) => endMs <= it.startMs);
      if (col === -1) {
        col = columns.length;
        columns.push(it.endMs);
      } else {
        columns[col] = it.endMs;
      }
      it.col = col;
      cluster.push(it);
      clusterEnd = Math.max(clusterEnd, it.endMs);
    }
    finalizeCluster();
    return items;
  }

  function renderBookings() {
    const columns = el.weekGrid.querySelectorAll('.day-column');
    columns.forEach((c) => { c.querySelectorAll('.booking-block').forEach((b) => b.remove()); });

    const byDay = [[], [], [], [], []];
    for (const booking of applyFilters(state.bookings)) {
      const start = new Date(booking.start_datetime.replace(' ', 'T'));
      const end = new Date(booking.end_datetime.replace(' ', 'T'));
      const dayIndex = dayIndexFor(start);
      if (dayIndex === null) continue;
      byDay[dayIndex].push({ booking, start, end, startMs: start.getTime(), endMs: end.getTime() });
    }

    for (let dayIndex = 0; dayIndex < 5; dayIndex++) {
      for (const item of layoutDayBookings(byDay[dayIndex])) {
        renderBookingBlock(item, columns[dayIndex]);
      }
    }
  }

  function renderBookingBlock(layoutItem, columnEl) {
    const { booking, start, end, col, totalCols } = layoutItem;
    const top = timeToOffsetPx(start);
    const bottom = timeToOffsetPx(end);
    const height = Math.max(bottom - top, 20);
    const colWidthPct = 100 / totalCols;

    const block = document.createElement('div');
    block.className = `booking-block status-${booking.status}` + (booking.client_logo_path ? ' has-logo' : '');
    block.style.top = `${top}px`;
    block.style.height = `${height}px`;
    block.style.left = `calc(${col * colWidthPct}% + 4px)`;
    block.style.width = `calc(${colWidthPct}% - 8px)`;

    const statusEl = document.createElement('span');
    statusEl.className = 'b-status';
    statusEl.textContent = STATUS_LABELS[booking.status] || booking.status;

    const titleEl = document.createElement('span');
    titleEl.className = 'b-title';
    titleEl.textContent = booking.title;

    const metaEl = document.createElement('span');
    metaEl.className = 'b-meta';
    const names = booking.attendees.map((a) => a.name).join(', ');
    metaEl.textContent = `${formatTime(start)}–${formatTime(end)}${names ? ' · ' + names : ''}`;

    const requiredItems = activeRequiredChecklistItems(booking);
    const prepDone = requiredItems.filter((item) => booking['checklist_' + item.key]).length;
    const prepEl = document.createElement('span');
    prepEl.className = 'b-prep' + (prepDone === requiredItems.length ? ' complete' : '');
    prepEl.textContent = `Prep ${prepDone}/${requiredItems.length}`;

    let noSyncEl = null;
    if (booking.skip_calendar_sync) {
      noSyncEl = document.createElement('span');
      noSyncEl.className = 'b-no-sync';
      noSyncEl.textContent = '🔕 Not synced';
      noSyncEl.title = 'This booking is not pushed to Google Calendar (already added manually)';
    }

    const checklistEl = document.createElement('div');
    checklistEl.className = 'b-checklist';
    checklistEl.addEventListener('click', (e) => e.stopPropagation());
    const checklistHeader = document.createElement('div');
    checklistHeader.className = 'b-checklist-header';
    checklistHeader.textContent = 'Pre-production checklist';
    checklistEl.appendChild(checklistHeader);
    for (const item of CHECKLIST_ITEMS) {
      checklistEl.appendChild(buildBlockChecklistRow(booking, 'checklist_' + item.key, item.label));
    }

    const attendeesEl = document.createElement('div');
    attendeesEl.className = 'b-attendees';
    for (const attendee of booking.attendees) {
      attendeesEl.appendChild(buildAvatarEl(attendee.name, 'avatar-lg', attendee.id));
    }

    let clientLogoEl = null;
    if (booking.client_logo_path) {
      clientLogoEl = document.createElement('img');
      clientLogoEl.className = 'b-client-logo';
      clientLogoEl.src = booking.client_logo_path;
      clientLogoEl.alt = booking.client_name || 'Client';
      clientLogoEl.title = booking.client_name || '';
    }

    let bookedByEl = null;
    if (booking.created_by_name) {
      bookedByEl = document.createElement('span');
      bookedByEl.className = 'b-booked-by';
      bookedByEl.textContent = `Booked by ${booking.created_by_name}`;
    }

    block.appendChild(statusEl);
    block.appendChild(titleEl);
    block.appendChild(metaEl);
    block.appendChild(prepEl);
    if (noSyncEl) block.appendChild(noSyncEl);
    block.appendChild(checklistEl);
    block.appendChild(attendeesEl);
    if (clientLogoEl) block.appendChild(clientLogoEl);
    if (bookedByEl) block.appendChild(bookedByEl);
    block.addEventListener('click', (e) => {
      e.stopPropagation();
      openEditModal(booking);
    });

    columnEl.appendChild(block);
  }

  function dayIndexFor(date) {
    const diffDays = Math.floor((stripTime(date) - state.weekStart) / 86400000);
    return diffDays >= 0 && diffDays < 5 ? diffDays : null;
  }

  function stripTime(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function updateWeekLabel() {
    const end = addDays(state.weekStart, 4);
    const fmt = (d) => d.toLocaleDateString([], { day: 'numeric', month: 'short' });
    const fmtWithYear = (d) => d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
    el.weekLabel.textContent = `${fmt(state.weekStart)} – ${fmt(end)}`;
    el.printHeader.textContent = `Film Plan — Week of ${fmtWithYear(state.weekStart)} – ${fmtWithYear(end)}`;
  }

  function updateWeekTally() {
    const total = state.bookings.length;
    const confirmed = state.bookings.filter((b) => b.status === 'confirmed').length;
    const pencil = total - confirmed;
    if (total === 0) {
      el.weekTally.textContent = 'Nothing booked this week yet — grab a pencil';
    } else {
      el.weekTally.textContent = `${total} booking${total === 1 ? '' : 's'} this week · ${pencil} pencil · ${confirmed} confirmed`;
    }
  }

  const AVATAR_COLORS = [
    '#e07a5f', '#3d5a80', '#81b29a', '#c08a2e', '#9b5de5', '#00a896', '#d64550', '#4a6fa5',
    '#2a9d8f', '#e76f51', '#6d597a', '#457b9d', '#b56576', '#588157', '#bc6c25', '#7209b7',
  ];
  const personColorById = {};

  async function loadPersonColors() {
    const all = await apiGet('api/people_list.php?include_inactive=1');
    const sorted = all.slice().sort((a, b) => a.id - b.id);
    sorted.forEach((p, i) => {
      personColorById[p.id] = AVATAR_COLORS[i % AVATAR_COLORS.length];
    });
  }

  function colorForPerson(id, name) {
    if (id != null && personColorById[id]) return personColorById[id];
    return colorForName(name);
  }

  function initialsForName(name) {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function setChecklistBy(el, name) {
    if (name) {
      el.textContent = initialsForName(name);
      el.title = name;
      el.classList.remove('hidden');
    } else {
      el.textContent = '';
      el.removeAttribute('title');
      el.classList.add('hidden');
    }
  }

  function colorForName(name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    return AVATAR_COLORS[hash % AVATAR_COLORS.length];
  }

  function updateWeekPeopleSummary() {
    const daysByPerson = {};
    for (const booking of state.bookings) {
      if (booking.status === 'cancelled') continue;
      const dateStr = booking.start_datetime.slice(0, 10);
      for (const attendee of booking.attendees) {
        if (!daysByPerson[attendee.id]) daysByPerson[attendee.id] = new Set();
        daysByPerson[attendee.id].add(dateStr);
      }
    }

    el.peopleSummaryList.innerHTML = '';
    if (state.people.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'people-summary-empty';
      empty.textContent = 'No people yet.';
      el.peopleSummaryList.appendChild(empty);
      return;
    }

    for (const person of state.people) {
      const count = daysByPerson[person.id] ? daysByPerson[person.id].size : 0;
      const row = document.createElement('div');
      row.className = 'people-summary-row';

      const avatar = document.createElement('span');
      avatar.className = 'psr-avatar';
      avatar.style.backgroundColor = colorForPerson(person.id, person.name);
      avatar.textContent = initialsForName(person.name);

      const info = document.createElement('span');
      info.className = 'psr-info';

      const name = document.createElement('span');
      name.className = 'psr-name';
      name.textContent = person.name;
      info.appendChild(name);

      if (person.role) {
        const role = document.createElement('span');
        role.className = 'psr-role';
        role.textContent = person.role;
        info.appendChild(role);
      }

      const badge = document.createElement('span');
      badge.className = 'psr-count' + (count === 0 ? ' zero' : '');
      badge.textContent = String(count);

      row.appendChild(avatar);
      row.appendChild(info);
      row.appendChild(badge);
      el.peopleSummaryList.appendChild(row);
    }
  }

  async function loadWeek() {
    updateWeekLabel();
    buildWeekSkeleton();
    const data = await apiGet(`api/bookings_list.php?week_start=${isoDate(state.weekStart)}`);
    state.bookings = data.bookings;
    renderBookings();
    updateWeekTally();
    updateWeekPeopleSummary();
    await loadKitUsage();
  }

  const KIT_LABELS = { fuzzy_duck: 'Fuzzy Duck kit', mark: "Mark's kit", tom: "Tom's kit" };

  async function loadKitUsage() {
    const monthDate = addDays(state.weekStart, 3); // mid-week, avoids month-boundary ambiguity
    const monthStr = `${monthDate.getFullYear()}-${String(monthDate.getMonth() + 1).padStart(2, '0')}`;
    const data = await apiGet(`api/kit_usage.php?month=${monthStr}`);
    const monthLabel = monthDate.toLocaleDateString([], { month: 'long', year: 'numeric' });
    el.kitUsageTitle.textContent = `Kit usage · ${monthLabel}`;
    el.kitUsageList.innerHTML = '';
    for (const key of ['fuzzy_duck', 'mark', 'tom']) {
      const row = document.createElement('div');
      row.className = 'kit-usage-row';
      const label = document.createElement('span');
      label.textContent = KIT_LABELS[key];
      const count = document.createElement('span');
      count.className = 'kit-usage-count';
      count.textContent = `${data.counts[key] || 0} day${data.counts[key] === 1 ? '' : 's'}`;
      row.appendChild(label);
      row.appendChild(count);
      el.kitUsageList.appendChild(row);
    }
  }

  function currentMonthStr(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  }

  function applyFilters(bookings) {
    return bookings.filter((b) => {
      if (state.filters.personId && !b.attendees.some((a) => String(a.id) === state.filters.personId)) return false;
      if (state.filters.clientId && String(b.client_id || '') !== state.filters.clientId) return false;
      if (state.filters.kit && b.kit_source !== state.filters.kit) return false;
      return true;
    });
  }

  async function loadMonth() {
    const monthStr = state.monthStr || currentMonthStr(new Date());
    state.monthStr = monthStr;
    const monthDate = new Date(monthStr + '-01T00:00:00');
    el.monthLabel.textContent = monthDate.toLocaleDateString([], { month: 'long', year: 'numeric' });
    const data = await apiGet(`api/bookings_month_list.php?month=${monthStr}`);
    state.monthBookings = data.bookings;
    renderMonthGrid(monthDate);
  }

  function shiftMonth(delta) {
    const [y, m] = (state.monthStr || currentMonthStr(new Date())).split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    state.monthStr = currentMonthStr(d);
    loadMonth();
  }

  function renderMonthGrid(monthDate) {
    el.monthGrid.innerHTML = '';
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth();
    const todayIso = isoDate(new Date());

    for (const name of DAY_NAMES) {
      const header = document.createElement('div');
      header.className = 'month-day-header';
      header.textContent = name;
      el.monthGrid.appendChild(header);
    }

    const bookingsByDate = {};
    for (const booking of applyFilters(state.monthBookings)) {
      const dateIso = booking.start_datetime.slice(0, 10);
      (bookingsByDate[dateIso] = bookingsByDate[dateIso] || []).push(booking);
    }

    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = [];
    for (let day = 1; day <= daysInMonth; day++) {
      const dateObj = new Date(year, month, day);
      const weekday = dateObj.getDay(); // 0=Sun..6=Sat
      if (weekday >= 1 && weekday <= 5) cells.push(dateObj);
    }
    if (cells.length) {
      const startCol = cells[0].getDay() - 1; // Mon=0..Fri=4
      for (let i = 0; i < startCol; i++) cells.unshift(null);
      while (cells.length % 5 !== 0) cells.push(null);
    }

    for (const dateObj of cells) {
      const cell = document.createElement('div');
      if (!dateObj) {
        cell.className = 'month-cell is-empty';
        el.monthGrid.appendChild(cell);
        continue;
      }
      const dateIso = isoDate(dateObj);
      const blocked = state.blockedDaysByDate[dateIso];
      cell.className = 'month-cell' + (dateIso === todayIso ? ' is-today' : '') + (blocked ? ' is-blocked' : '');
      cell.addEventListener('click', (e) => {
        if (e.target !== cell) return;
        if (blocked) {
          alert(`This day is blocked for bookings${blocked.reason ? ': ' + blocked.reason : ''}.`);
          return;
        }
        openAddModal(dateObj);
      });

      const dateLabel = document.createElement('div');
      dateLabel.className = 'month-cell-date';
      dateLabel.textContent = String(dateObj.getDate());
      cell.appendChild(dateLabel);

      if (blocked) {
        const reasonEl = document.createElement('div');
        reasonEl.className = 'day-blocked-reason';
        reasonEl.textContent = blocked.reason || 'Blocked';
        cell.appendChild(reasonEl);
      }

      const unavailBadge = buildUnavailableBadge(dateIso);
      if (unavailBadge) cell.appendChild(unavailBadge);

      for (const booking of (bookingsByDate[dateIso] || [])) {
        const pill = document.createElement('div');
        pill.className = `month-pill status-${booking.status}`;
        pill.textContent = booking.title;
        pill.title = booking.title;
        pill.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(booking); });
        cell.appendChild(pill);
      }

      el.monthGrid.appendChild(cell);
    }
  }

  async function loadKitUsagePage() {
    const monthStr = state.kitUsagePageMonth || currentMonthStr(new Date());
    state.kitUsagePageMonth = monthStr;
    const data = await apiGet(`api/kit_usage.php?month=${monthStr}`);
    const monthDate = new Date(monthStr + '-01T00:00:00');
    el.kitUsagePageMonthLabel.textContent = monthDate.toLocaleDateString([], { month: 'long', year: 'numeric' });

    el.kitUsagePageSummary.innerHTML = '';
    for (const key of ['fuzzy_duck', 'mark', 'tom']) {
      const tile = document.createElement('div');
      tile.className = 'kupsummary-tile';
      const count = document.createElement('span');
      count.className = 'kupsummary-count';
      count.textContent = data.counts[key] || 0;
      const label = document.createElement('span');
      label.className = 'kupsummary-label';
      label.textContent = `${KIT_LABELS[key]} — days`;
      tile.appendChild(count);
      tile.appendChild(label);
      el.kitUsagePageSummary.appendChild(tile);
    }

    el.kitUsagePageTableBody.innerHTML = '';
    if (data.entries.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 4;
      cell.textContent = 'No bookings this month.';
      cell.style.color = 'var(--muted)';
      row.appendChild(cell);
      el.kitUsagePageTableBody.appendChild(row);
      return;
    }
    for (const entry of data.entries) {
      const row = document.createElement('tr');
      const start = new Date(entry.start_datetime.replace(' ', 'T'));

      const dateCell = document.createElement('td');
      dateCell.textContent = start.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });

      const titleCell = document.createElement('td');
      titleCell.textContent = entry.title;

      const kitCell = document.createElement('td');
      kitCell.textContent = KIT_LABELS[entry.kit_source] || entry.kit_source;

      const statusCell = document.createElement('td');
      const pill = document.createElement('span');
      pill.className = `status-pill ${entry.status === 'confirmed' ? 'active' : 'inactive'}`;
      pill.textContent = entry.status === 'confirmed' ? 'Confirmed' : 'Pencil';
      statusCell.appendChild(pill);

      row.appendChild(dateCell);
      row.appendChild(titleCell);
      row.appendChild(kitCell);
      row.appendChild(statusCell);
      el.kitUsagePageTableBody.appendChild(row);
    }
  }

  function shiftKitUsagePageMonth(delta) {
    const monthStr = state.kitUsagePageMonth || currentMonthStr(new Date());
    const d = new Date(monthStr + '-01T00:00:00');
    d.setMonth(d.getMonth() + delta);
    state.kitUsagePageMonth = currentMonthStr(d);
    loadKitUsagePage();
  }

  async function loadPeople() {
    state.people = await apiGet('api/people_list.php');
    el.fieldAttendees.innerHTML = '';
    el.filterPerson.innerHTML = '<option value="">All people</option>';
    for (const p of state.people) {
      const opt = document.createElement('option');
      opt.value = String(p.id);
      opt.textContent = p.name;
      el.fieldAttendees.appendChild(opt);
      el.filterPerson.appendChild(opt.cloneNode(true));
    }
    if (state.bookings.length || state.people.length) updateWeekPeopleSummary();
  }

  async function loadClients() {
    state.clients = await apiGet('api/clients_list.php');
    el.fieldClient.innerHTML = '';
    el.filterClient.innerHTML = '<option value="">All clients</option>';
    const noneOpt = document.createElement('option');
    noneOpt.value = '';
    noneOpt.textContent = '— None —';
    el.fieldClient.appendChild(noneOpt);
    for (const c of state.clients) {
      const opt = document.createElement('option');
      opt.value = String(c.id);
      opt.textContent = c.name;
      el.fieldClient.appendChild(opt);
      el.filterClient.appendChild(opt.cloneNode(true));
    }
  }

  function onFilterChange() {
    state.filters.personId = el.filterPerson.value;
    state.filters.clientId = el.filterClient.value;
    state.filters.kit = el.filterKit.value;
    const active = !!(state.filters.personId || state.filters.clientId || state.filters.kit);
    el.clearFiltersBtn.classList.toggle('hidden', !active);
    if (state.currentView === 'month') {
      renderMonthGrid(new Date((state.monthStr || currentMonthStr(new Date())) + '-01T00:00:00'));
    } else {
      renderBookings();
      updateWeekTally();
    }
  }

  function clearFilters() {
    state.filters = { personId: '', clientId: '', kit: '' };
    el.filterPerson.value = '';
    el.filterClient.value = '';
    el.filterKit.value = '';
    onFilterChange();
  }

  async function refreshBookings() {
    await loadWeek();
    if (state.currentView === 'month') {
      await loadMonth();
    }
    if (state.currentView === 'admin' && state.adminSubView === 'needsPrep') {
      await loadNeedsPrepTable();
    }
  }

  function findLoadedBooking(id) {
    return state.bookings.find((b) => b.id === id) || state.monthBookings.find((b) => b.id === id);
  }

  async function loadBlockedDays() {
    const rows = await apiGet('api/blocked_days_list.php');
    state.blockedDaysByDate = {};
    for (const row of rows) {
      state.blockedDaysByDate[row.day] = row;
    }
  }

  async function loadUnavailability() {
    const rows = await apiGet('api/person_unavailable_list.php');
    state.unavailableByDate = {};
    for (const row of rows) {
      (state.unavailableByDate[row.day] = state.unavailableByDate[row.day] || []).push(row);
    }
  }

  async function loadRecurringUnavailability() {
    state.recurringUnavailability = await apiGet('api/person_recurring_unavailable_list.php');
  }

  function isoWeekdayOf(dateObj) {
    const jsDay = dateObj.getDay();
    return jsDay === 0 ? 7 : jsDay;
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

  function unavailableEntriesForDate(dateIso) {
    const explicit = state.unavailableByDate[dateIso] || [];
    const weekday = isoWeekdayOf(new Date(dateIso + 'T00:00:00'));
    const recurring = state.recurringUnavailability
      .filter((r) => r.weekday === weekday && recurringRuleMatchesDate(r, dateIso))
      .map((r) => ({ person_id: r.person_id, person_name: r.person_name, period: r.period, reason: r.reason, recurring: true }));
    return explicit.concat(recurring);
  }

  async function onDayLockClick(dateIso, e) {
    e.stopPropagation();
    const existing = state.blockedDaysByDate[dateIso];
    if (existing) {
      if (!confirm(`Unblock ${dateIso}? (${existing.reason || 'no reason given'})`)) return;
      await apiPost(`api/blocked_days_delete.php?id=${existing.id}`);
    } else {
      const reason = prompt(`Block ${dateIso} for bookings — reason (e.g. "PAT testing, kit in office"):`);
      if (reason === null) return;
      try {
        await apiPost('api/blocked_days_create.php', { day: dateIso, reason });
      } catch (err) {
        alert(err.message);
        return;
      }
    }
    await loadBlockedDays();
    await refreshBookings();
  }

  async function loadBlockedDaysTable() {
    const rows = await apiGet('api/blocked_days_list.php');
    el.blockedDaysTableBody.innerHTML = '';
    if (rows.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 3;
      cell.textContent = 'No blocked days.';
      cell.style.color = 'var(--muted)';
      row.appendChild(cell);
      el.blockedDaysTableBody.appendChild(row);
      return;
    }
    for (const bd of rows) {
      const row = document.createElement('tr');

      const dateCell = document.createElement('td');
      const d = new Date(bd.day + 'T00:00:00');
      dateCell.textContent = d.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

      const reasonCell = document.createElement('td');
      reasonCell.textContent = bd.reason || '';

      const actionsCell = document.createElement('td');
      actionsCell.className = 'actions';
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Unblock';
      removeBtn.addEventListener('click', async () => {
        await apiPost(`api/blocked_days_delete.php?id=${bd.id}`);
        await loadBlockedDays();
        await loadBlockedDaysTable();
        await refreshBookings();
      });
      actionsCell.appendChild(removeBtn);

      row.appendChild(dateCell);
      row.appendChild(reasonCell);
      row.appendChild(actionsCell);
      el.blockedDaysTableBody.appendChild(row);
    }
  }

  async function loadNeedsPrepTable() {
    const data = await apiGet('api/bookings_needs_prep.php');
    el.needsPrepTableBody.innerHTML = '';
    if (data.bookings.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 5;
      cell.textContent = 'Nothing outstanding — every upcoming booking is fully prepped.';
      cell.className = 'muted-note';
      row.appendChild(cell);
      el.needsPrepTableBody.appendChild(row);
      return;
    }
    for (const booking of data.bookings) {
      const row = document.createElement('tr');
      row.className = 'clickable-row';
      row.addEventListener('click', () => openEditModal(booking));

      const dateCell = document.createElement('td');
      const start = new Date(booking.start_datetime.replace(' ', 'T'));
      dateCell.textContent = start.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

      const titleCell = document.createElement('td');
      titleCell.textContent = booking.title;

      const clientCell = document.createElement('td');
      clientCell.textContent = booking.client_name || '';

      const statusCell = document.createElement('td');
      const pill = document.createElement('span');
      pill.className = `prep-status-pill status-${booking.status}`;
      pill.textContent = STATUS_LABELS[booking.status] || booking.status;
      statusCell.appendChild(pill);

      const missingCell = document.createElement('td');
      missingCell.className = 'needs-prep-missing';
      const missing = activeRequiredChecklistItems(booking).filter((item) => !booking['checklist_' + item.key]).map((item) => item.label);
      missingCell.textContent = missing.join(', ');

      row.appendChild(dateCell);
      row.appendChild(titleCell);
      row.appendChild(clientCell);
      row.appendChild(statusCell);
      row.appendChild(missingCell);
      el.needsPrepTableBody.appendChild(row);
    }
  }

  function hideBlockedDayFormError() {
    el.blockedDayFormError.classList.add('hidden');
  }

  function showBlockedDayFormError(message) {
    el.blockedDayFormError.textContent = message;
    el.blockedDayFormError.classList.remove('hidden');
  }

  async function onBlockedDayFormSubmit(e) {
    e.preventDefault();
    hideBlockedDayFormError();
    try {
      await apiPost('api/blocked_days_create.php', {
        day: el.blockedDayDate.value,
        reason: el.blockedDayReason.value.trim(),
      });
      el.blockedDayForm.reset();
      await loadBlockedDays();
      await loadBlockedDaysTable();
      await refreshBookings();
    } catch (err) {
      showBlockedDayFormError(err.message);
    }
  }

  function onDayColumnClick(dayIndex) {
    const date = addDays(state.weekStart, dayIndex);
    if (state.blockedDaysByDate[isoDate(date)]) {
      const reason = state.blockedDaysByDate[isoDate(date)].reason;
      alert(`This day is blocked for bookings${reason ? ': ' + reason : ''}.`);
      return;
    }
    openAddModal(date);
  }

  function updateLocationMapsLink() {
    const value = el.fieldLocation.value.trim();
    if (value) {
      el.fieldLocationMapsLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(value);
      el.fieldLocationMapsLink.classList.remove('hidden');
    } else {
      el.fieldLocationMapsLink.classList.add('hidden');
    }
  }

  function updateWhat3WordsLink() {
    const value = el.fieldWhat3Words.value.trim().replace(/^\/+/, '');
    if (value) {
      el.fieldWhat3WordsLink.href = 'https://what3words.com/' + encodeURIComponent(value);
      el.fieldWhat3WordsLink.classList.remove('hidden');
    } else {
      el.fieldWhat3WordsLink.classList.add('hidden');
    }
  }

  function updateChecklistUrlLink(item) {
    const input = el['fieldChecklist' + item.elKey + 'Url'];
    const link = el['fieldChecklist' + item.elKey + 'UrlLink'];
    const value = input.value.trim();
    if (value) {
      link.href = value;
      link.classList.remove('hidden');
    } else {
      link.classList.add('hidden');
    }
  }

  function updateShotListNaState() {
    const na = el.fieldChecklistShotListNa.checked;
    if (na) {
      el.fieldChecklistShotList.checked = false;
      setChecklistBy(el.fieldChecklistShotListBy, null);
    }
    el.fieldChecklistShotList.disabled = na;
    el.fieldChecklistShotListUrl.disabled = na;
    el.fieldChecklistShotListUrl.value = na ? '' : el.fieldChecklistShotListUrl.value;
    updateChecklistUrlLink(CHECKLIST_ITEMS.find((item) => item.key === 'shot_list'));
  }

  function openAllAttachedDocuments() {
    const urls = CHECKLIST_ITEMS
      .map((item) => el['fieldChecklist' + item.elKey + 'Url'].value.trim())
      .filter(Boolean);
    if (!urls.length) {
      alert('No documents attached to this booking yet.');
      return;
    }
    for (const url of urls) {
      window.open(url, '_blank', 'noopener');
    }
  }

  let conflictCheckTimer = null;
  function scheduleConflictCheck() {
    clearTimeout(conflictCheckTimer);
    conflictCheckTimer = setTimeout(checkForConflicts, 350);
  }

  async function checkForConflicts() {
    const attendeeIds = Array.from(el.fieldAttendees.selectedOptions).map((o) => Number(o.value));
    if (attendeeIds.length === 0 || !el.fieldDate.value || !el.fieldStart.value || !el.fieldEnd.value) {
      el.conflictWarning.classList.add('hidden');
      return;
    }
    const payload = {
      start_datetime: `${el.fieldDate.value}T${el.fieldStart.value}`,
      end_datetime: `${el.fieldDate.value}T${el.fieldEnd.value}`,
      attendee_ids: attendeeIds,
      exclude_booking_id: state.editingId,
    };
    let data;
    try {
      data = await apiPost('api/check_conflicts.php', payload);
    } catch (err) {
      return; // silent — this is a convenience check, not critical path
    }
    const unavailable = unavailableEntriesForDate(el.fieldDate.value)
      .filter((u) => attendeeIds.includes(u.person_id) &&
        unavailabilityOverlapsTime(u.period, el.fieldStart.value, el.fieldEnd.value));

    if (!data.conflicts.length && !unavailable.length) {
      el.conflictWarning.classList.add('hidden');
      return;
    }
    el.conflictWarning.innerHTML = '';
    if (data.conflicts.length) {
      const title = document.createElement('span');
      title.className = 'cw-title';
      title.textContent = `Possible double-booking (${data.conflicts.length}):`;
      el.conflictWarning.appendChild(title);
      for (const c of data.conflicts) {
        const start = new Date(c.start_datetime.replace(' ', 'T'));
        const end = new Date(c.end_datetime.replace(' ', 'T'));
        const item = document.createElement('span');
        item.className = 'cw-item';
        item.textContent = `${c.person_name} is already on "${c.booking_title}" ${formatTime(start)}–${formatTime(end)}`;
        el.conflictWarning.appendChild(item);
      }
    }
    if (unavailable.length) {
      const title = document.createElement('span');
      title.className = 'cw-title';
      title.textContent = `Marked unavailable (${unavailable.length}):`;
      el.conflictWarning.appendChild(title);
      const periodPhrase = { all_day: 'this day', am: 'this morning', pm: 'this afternoon' };
      for (const u of unavailable) {
        const item = document.createElement('span');
        item.className = 'cw-item';
        item.textContent = `${u.person_name} is unavailable ${periodPhrase[u.period]}${u.reason ? ': ' + u.reason : ''}`;
        el.conflictWarning.appendChild(item);
      }
    }
    el.conflictWarning.classList.remove('hidden');
  }

  function unavailabilityOverlapsTime(period, startTime, endTime) {
    if (period === 'all_day') return true;
    if (period === 'am') return startTime < '12:00';
    return endTime > '12:00';
  }

  function openAddModal(date) {
    state.editingId = null;
    el.modalTitle.textContent = 'New booking';
    el.bookingForm.reset();
    el.fieldDate.value = isoDate(date);
    el.fieldStart.value = '09:00';
    el.fieldEnd.value = '17:00';
    el.fieldClient.value = '';
    el.fieldKitSource.value = 'fuzzy_duck';
    updateLocationMapsLink();
    updateWhat3WordsLink();
    for (const item of CHECKLIST_ITEMS) {
      el['fieldChecklist' + item.elKey].checked = false;
      el['fieldChecklist' + item.elKey + 'Url'].value = '';
      setChecklistBy(el['fieldChecklist' + item.elKey + 'By'], null);
      updateChecklistUrlLink(item);
    }
    el.fieldChecklistShotListNa.checked = false;
    updateShotListNaState();
    el.openAllDocsBtn.classList.add('hidden');
    el.fieldSkipCalendarSync.checked = false;
    el.deleteBookingBtn.classList.add('hidden');
    el.confirmBookingBtn.classList.add('hidden');
    el.unconfirmBookingBtn.classList.add('hidden');
    el.emailConfirmationBtn.classList.add('hidden');
    el.formError.classList.add('hidden');
    el.syncResults.classList.add('hidden');
    el.conflictWarning.classList.add('hidden');
    el.modalBackdrop.classList.remove('hidden');
  }

  function openEditModal(booking) {
    state.editingId = booking.id;
    el.modalTitle.textContent = booking.title;
    const start = new Date(booking.start_datetime.replace(' ', 'T'));
    const end = new Date(booking.end_datetime.replace(' ', 'T'));
    el.fieldTitle.value = booking.title;
    el.fieldDate.value = isoDate(start);
    el.fieldStart.value = pad2(start.getHours()) + ':' + pad2(start.getMinutes());
    el.fieldEnd.value = pad2(end.getHours()) + ':' + pad2(end.getMinutes());
    el.fieldLocation.value = booking.location || '';
    el.fieldWhat3Words.value = booking.what3words || '';
    el.fieldClient.value = booking.client_id ? String(booking.client_id) : '';
    el.fieldKitSource.value = booking.kit_source || 'fuzzy_duck';
    updateLocationMapsLink();
    updateWhat3WordsLink();
    el.fieldNotes.value = booking.notes || '';
    for (const item of CHECKLIST_ITEMS) {
      const done = !!booking['checklist_' + item.key];
      el['fieldChecklist' + item.elKey].checked = done;
      el['fieldChecklist' + item.elKey + 'Url'].value = booking['checklist_' + item.key + '_url'] || '';
      setChecklistBy(el['fieldChecklist' + item.elKey + 'By'], done ? booking['checklist_' + item.key + '_by'] : null);
      updateChecklistUrlLink(item);
    }
    el.fieldChecklistShotListNa.checked = !!booking.checklist_shot_list_na;
    updateShotListNaState();
    const anyDocUrl = CHECKLIST_ITEMS.some((item) => booking['checklist_' + item.key + '_url']);
    el.openAllDocsBtn.classList.toggle('hidden', !anyDocUrl);
    el.fieldSkipCalendarSync.checked = !!booking.skip_calendar_sync;
    const attendeeIds = new Set(booking.attendees.map((a) => a.id));
    for (const opt of el.fieldAttendees.options) {
      opt.selected = attendeeIds.has(Number(opt.value));
    }
    el.deleteBookingBtn.classList.remove('hidden');
    el.deleteBookingBtn.textContent = booking.status === 'confirmed' ? 'Cancel booking' : 'Delete';
    el.confirmBookingBtn.classList.toggle('hidden', booking.status !== 'pencil');
    el.unconfirmBookingBtn.classList.toggle('hidden', booking.status !== 'confirmed');
    el.emailConfirmationBtn.classList.toggle('hidden', booking.status !== 'confirmed');
    el.formError.classList.add('hidden');
    el.syncResults.classList.add('hidden');
    el.conflictWarning.classList.add('hidden');
    el.modalBackdrop.classList.remove('hidden');
    scheduleConflictCheck();
  }

  function closeModal() {
    el.modalBackdrop.classList.add('hidden');
    state.editingId = null;
  }

  function pad2(n) { return String(n).padStart(2, '0'); }

  function showFormError(message) {
    el.formError.textContent = message;
    el.formError.classList.remove('hidden');
  }

  async function onFormSubmit(e) {
    e.preventDefault();
    const attendeeIds = Array.from(el.fieldAttendees.selectedOptions).map((o) => Number(o.value));
    if (attendeeIds.length === 0) {
      showFormError('Select at least one attendee.');
      return;
    }
    const payload = {
      title: el.fieldTitle.value.trim(),
      location: el.fieldLocation.value.trim(),
      what3words: el.fieldWhat3Words.value.trim(),
      client_id: el.fieldClient.value ? Number(el.fieldClient.value) : null,
      kit_source: el.fieldKitSource.value,
      notes: el.fieldNotes.value.trim(),
      start_datetime: `${el.fieldDate.value}T${el.fieldStart.value}`,
      end_datetime: `${el.fieldDate.value}T${el.fieldEnd.value}`,
      attendee_ids: attendeeIds,
      skip_calendar_sync: el.fieldSkipCalendarSync.checked,
    };
    for (const item of CHECKLIST_ITEMS) {
      payload['checklist_' + item.key] = el['fieldChecklist' + item.elKey].checked;
      payload['checklist_' + item.key + '_url'] = el['fieldChecklist' + item.elKey + 'Url'].value.trim();
    }
    payload.checklist_shot_list_na = el.fieldChecklistShotListNa.checked;

    try {
      let data;
      if (state.editingId) {
        data = await apiPost(`api/bookings_update.php?id=${state.editingId}`, payload);
      } else {
        data = await apiPost('api/bookings_create.php', payload);
      }
      closeModal();
      await refreshBookings();
      const failures = (data.sync_results || []).filter((r) => r.status === 'error');
      if (failures.length) {
        alert('Booking saved, but some calendar updates failed:\n' +
          failures.map((r) => `${r.person}: ${r.error}`).join('\n'));
      }
    } catch (err) {
      showFormError(err.message);
    }
  }

  function showSyncResults(results) {
    el.syncResults.innerHTML = '';
    for (const r of results) {
      const row = document.createElement('div');
      const label = document.createElement('span');
      label.textContent = r.person;
      const status = document.createElement('span');
      status.className = r.status === 'error' ? 'sync-error' : 'sync-ok';
      status.textContent = r.status === 'error' ? `Failed: ${r.error}` : 'Synced to calendar';
      row.appendChild(label);
      row.appendChild(status);
      el.syncResults.appendChild(row);
    }
    el.syncResults.classList.remove('hidden');
  }

  async function onConfirmClick() {
    if (!state.editingId) return;
    el.confirmBookingBtn.disabled = true;
    try {
      const data = await apiPost(`api/bookings_confirm.php?id=${state.editingId}`);
      await refreshBookings();
      const updated = findLoadedBooking(state.editingId);
      if (updated) openEditModal(updated);
      showSyncResults(data.results);
    } catch (err) {
      showFormError(err.message);
    } finally {
      el.confirmBookingBtn.disabled = false;
    }
  }

  async function onEmailConfirmationClick() {
    if (!state.editingId) return;
    el.emailConfirmationBtn.disabled = true;
    el.emailPreviewError.classList.add('hidden');
    try {
      const data = await apiGet(`api/bookings_email_preview.php?id=${state.editingId}`);
      if (!data.recipients.length) {
        alert('This booking has no attendees to email.');
        return;
      }
      el.emailPreviewRecipients.textContent = 'To: ' + data.recipients.map((r) => `${r.name} <${r.email}>`).join(', ');
      el.emailPreviewSubject.textContent = 'Subject: ' + data.subject;
      el.emailPreviewFrame.srcdoc = data.html;
      el.sendEmailPreviewBtn.disabled = false;
      el.emailPreviewBackdrop.classList.remove('hidden');
    } catch (err) {
      alert(err.message);
    } finally {
      el.emailConfirmationBtn.disabled = false;
    }
  }

  function closeEmailPreview() {
    el.emailPreviewBackdrop.classList.add('hidden');
    el.emailPreviewFrame.srcdoc = '';
  }

  async function onSendEmailPreviewClick() {
    if (!state.editingId) return;
    el.sendEmailPreviewBtn.disabled = true;
    el.emailPreviewError.classList.add('hidden');
    try {
      const data = await apiPost(`api/bookings_email_confirmation.php?id=${state.editingId}`);
      const failures = (data.email_results || []).filter((r) => r.status === 'error');
      closeEmailPreview();
      if (failures.length) {
        alert('Some confirmation emails failed to send:\n' +
          failures.map((r) => `${r.person}: ${r.error}`).join('\n'));
      } else {
        alert(`Confirmation email sent to ${data.email_results.length} attendee${data.email_results.length === 1 ? '' : 's'}.`);
      }
    } catch (err) {
      el.emailPreviewError.textContent = err.message;
      el.emailPreviewError.classList.remove('hidden');
    } finally {
      el.sendEmailPreviewBtn.disabled = false;
    }
  }

  async function onUnconfirmClick() {
    if (!state.editingId) return;
    el.unconfirmBookingBtn.disabled = true;
    try {
      const data = await apiPost(`api/bookings_unconfirm.php?id=${state.editingId}`);
      await refreshBookings();
      const updated = findLoadedBooking(state.editingId);
      if (updated) openEditModal(updated);
      showSyncResults(data.results);
    } catch (err) {
      showFormError(err.message);
    } finally {
      el.unconfirmBookingBtn.disabled = false;
    }
  }

  async function onDeleteClick() {
    if (!state.editingId) return;
    if (!confirm('Remove this booking?')) return;
    try {
      await apiPost(`api/bookings_cancel.php?id=${state.editingId}`);
      closeModal();
      await refreshBookings();
    } catch (err) {
      showFormError(err.message);
    }
  }

  function switchView(view) {
    state.currentView = view;
    const isWeek = view === 'week';
    const isMonth = view === 'month';
    const isPeople = view === 'people';
    const isAdmin = view === 'admin';
    el.viewWeekBtn.classList.toggle('active', isWeek);
    el.viewMonthBtn.classList.toggle('active', isMonth);
    el.viewPeopleBtn.classList.toggle('active', isPeople);
    el.viewAdminBtn.classList.toggle('active', isAdmin);
    el.weekBody.classList.toggle('hidden', !isWeek);
    el.weekControls.classList.toggle('hidden', !isWeek);
    el.weekLegend.classList.toggle('hidden', !isWeek && !isMonth);
    el.addBookingBtn.classList.toggle('hidden', !isWeek && !isMonth);
    el.downloadWeekBtn.classList.toggle('hidden', !isWeek);
    el.monthView.classList.toggle('hidden', !isMonth);
    el.peopleView.classList.toggle('hidden', !isPeople);
    el.adminView.classList.toggle('hidden', !isAdmin);
    el.kitUsagePanel.classList.toggle('hidden', !isWeek);
    if (isPeople) loadPeopleTable();
    if (isAdmin) switchAdminSubView(state.adminSubView);
    if (isMonth) loadMonth();
  }

  function switchAdminSubView(subView) {
    state.adminSubView = subView;
    const isClients = subView === 'clients';
    const isKitUsage = subView === 'kitUsage';
    const isBlockedDays = subView === 'blockedDays';
    const isNeedsPrep = subView === 'needsPrep';
    el.adminTabClientsBtn.classList.toggle('active', isClients);
    el.adminTabKitUsageBtn.classList.toggle('active', isKitUsage);
    el.adminTabBlockedDaysBtn.classList.toggle('active', isBlockedDays);
    el.adminTabNeedsPrepBtn.classList.toggle('active', isNeedsPrep);
    el.clientsView.classList.toggle('hidden', !isClients);
    el.kitUsagePageView.classList.toggle('hidden', !isKitUsage);
    el.blockedDaysView.classList.toggle('hidden', !isBlockedDays);
    el.needsPrepView.classList.toggle('hidden', !isNeedsPrep);
    if (isClients) loadClientsTable();
    if (isKitUsage) loadKitUsagePage();
    if (isBlockedDays) loadBlockedDaysTable();
    if (isNeedsPrep) loadNeedsPrepTable();
  }

  function hidePersonFormError() {
    el.personFormError.classList.add('hidden');
  }

  function showPersonFormError(message) {
    el.personFormError.textContent = message;
    el.personFormError.classList.remove('hidden');
  }

  async function loadPeopleTable() {
    const people = await apiGet('api/people_list.php?include_inactive=1');
    el.peopleTableBody.innerHTML = '';
    for (const person of people) {
      el.peopleTableBody.appendChild(buildPersonRow(person));
      el.peopleTableBody.appendChild(buildUnavailablePanelRow(person));
    }
  }

  function buildUnavailablePanelRow(person) {
    const tr = document.createElement('tr');
    tr.className = 'unavailable-panel-row hidden';

    const td = document.createElement('td');
    td.colSpan = 5;

    const panel = document.createElement('div');
    panel.className = 'unavailable-panel';

    const list = document.createElement('div');
    list.className = 'unavailable-list';
    panel.appendChild(list);

    const form = document.createElement('form');
    form.className = 'unavailable-form';

    const dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.required = true;

    const periodSelect = document.createElement('select');
    for (const [value, label] of [['all_day', 'All day'], ['am', 'Morning (AM)'], ['pm', 'Afternoon (PM)']]) {
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      periodSelect.appendChild(opt);
    }

    const reasonInput = document.createElement('input');
    reasonInput.type = 'text';
    reasonInput.placeholder = 'Reason (optional)';

    const addBtn = document.createElement('button');
    addBtn.type = 'submit';
    addBtn.className = 'primary';
    addBtn.textContent = `Mark ${person.name.split(' ')[0]} unavailable`;

    form.appendChild(dateInput);
    form.appendChild(periodSelect);
    form.appendChild(reasonInput);
    form.appendChild(addBtn);

    const errorEl = document.createElement('p');
    errorEl.className = 'form-error hidden';

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorEl.classList.add('hidden');
      try {
        await apiPost('api/person_unavailable_create.php', {
          person_id: person.id,
          day: dateInput.value,
          period: periodSelect.value,
          reason: reasonInput.value.trim(),
        });
        dateInput.value = '';
        periodSelect.value = 'all_day';
        reasonInput.value = '';
        await refreshUnavailablePanel(list, person);
        await loadUnavailability();
        await refreshBookings();
      } catch (err) {
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
      }
    });

    panel.appendChild(form);
    panel.appendChild(errorEl);

    const recurringHeading = document.createElement('h4');
    recurringHeading.className = 'unavailable-panel-subheading';
    recurringHeading.textContent = 'Recurring weekly';
    panel.appendChild(recurringHeading);

    const recurringList = document.createElement('div');
    recurringList.className = 'unavailable-list recurring-list';
    panel.appendChild(recurringList);

    const recurringForm = document.createElement('form');
    recurringForm.className = 'unavailable-form';

    const weekdaySelect = document.createElement('select');
    WEEKDAY_LABELS.forEach((label, i) => {
      const opt = document.createElement('option');
      opt.value = String(i + 1);
      opt.textContent = label;
      weekdaySelect.appendChild(opt);
    });

    const recurringPeriodSelect = document.createElement('select');
    for (const [value, label] of [['all_day', 'All day'], ['am', 'Morning (AM)'], ['pm', 'Afternoon (PM)']]) {
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      recurringPeriodSelect.appendChild(opt);
    }

    const intervalSelect = document.createElement('select');
    for (const [value, label] of [['1', 'Every week'], ['2', 'Every 2 weeks'], ['3', 'Every 3 weeks'], ['4', 'Every 4 weeks']]) {
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = label;
      intervalSelect.appendChild(opt);
    }

    const anchorDateInput = document.createElement('input');
    anchorDateInput.type = 'date';
    anchorDateInput.title = 'Starting date (which week the pattern counts from)';
    anchorDateInput.classList.add('hidden');

    intervalSelect.addEventListener('change', () => {
      anchorDateInput.classList.toggle('hidden', intervalSelect.value === '1');
      anchorDateInput.required = intervalSelect.value !== '1';
    });

    const recurringReasonInput = document.createElement('input');
    recurringReasonInput.type = 'text';
    recurringReasonInput.placeholder = 'Reason (optional)';

    const recurringAddBtn = document.createElement('button');
    recurringAddBtn.type = 'submit';
    recurringAddBtn.className = 'primary';
    recurringAddBtn.textContent = `Add recurring rule`;

    recurringForm.appendChild(weekdaySelect);
    recurringForm.appendChild(intervalSelect);
    recurringForm.appendChild(anchorDateInput);
    recurringForm.appendChild(recurringReasonInput);
    recurringForm.appendChild(recurringAddBtn);

    const recurringErrorEl = document.createElement('p');
    recurringErrorEl.className = 'form-error hidden';

    recurringForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      recurringErrorEl.classList.add('hidden');
      try {
        await apiPost('api/person_recurring_unavailable_create.php', {
          person_id: person.id,
          weekday: Number(weekdaySelect.value),
          interval_weeks: Number(intervalSelect.value),
          anchor_date: intervalSelect.value !== '1' ? anchorDateInput.value : '',
          period: recurringPeriodSelect.value,
          reason: recurringReasonInput.value.trim(),
        });
        recurringReasonInput.value = '';
        recurringPeriodSelect.value = 'all_day';
        intervalSelect.value = '1';
        anchorDateInput.value = '';
        anchorDateInput.classList.add('hidden');
        await refreshRecurringPanel(recurringList, person);
        await loadRecurringUnavailability();
        await refreshBookings();
      } catch (err) {
        recurringErrorEl.textContent = err.message;
        recurringErrorEl.classList.remove('hidden');
      }
    });

    panel.appendChild(recurringForm);
    panel.appendChild(recurringErrorEl);

    td.appendChild(panel);
    tr.appendChild(td);
    return tr;
  }

  async function refreshUnavailablePanel(list, person) {
    list.innerHTML = '';
    const rows = await apiGet(`api/person_unavailable_list.php?person_id=${person.id}`);
    if (!rows.length) {
      const empty = document.createElement('p');
      empty.className = 'muted-note';
      empty.textContent = 'No unavailable days recorded.';
      list.appendChild(empty);
      return;
    }
    for (const r of rows) {
      const item = document.createElement('div');
      item.className = 'unavailable-item';
      const label = document.createElement('span');
      const d = new Date(r.day + 'T00:00:00');
      const periodSuffix = r.period !== 'all_day' ? ` (${UNAVAILABLE_PERIOD_LABELS[r.period]})` : '';
      label.textContent = d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) +
        periodSuffix + (r.reason ? ` — ${r.reason}` : '');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Remove';
      removeBtn.addEventListener('click', async () => {
        await apiPost(`api/person_unavailable_delete.php?id=${r.id}`);
        await refreshUnavailablePanel(list, person);
        await loadUnavailability();
        await refreshBookings();
      });
      item.appendChild(label);
      item.appendChild(removeBtn);
      list.appendChild(item);
    }
  }

  async function refreshRecurringPanel(list, person) {
    list.innerHTML = '';
    const rows = await apiGet(`api/person_recurring_unavailable_list.php?person_id=${person.id}`);
    if (!rows.length) {
      const empty = document.createElement('p');
      empty.className = 'muted-note';
      empty.textContent = 'No recurring rules.';
      list.appendChild(empty);
      return;
    }
    for (const r of rows) {
      const item = document.createElement('div');
      item.className = 'unavailable-item';
      const label = document.createElement('span');
      const periodSuffix = r.period !== 'all_day' ? ` (${UNAVAILABLE_PERIOD_LABELS[r.period]})` : '';
      const cadence = r.interval_weeks > 1
        ? `Every ${r.interval_weeks} weeks on ${WEEKDAY_LABELS[r.weekday - 1]} (from ${r.anchor_date})`
        : `Every ${WEEKDAY_LABELS[r.weekday - 1]}`;
      label.textContent = cadence + periodSuffix + (r.reason ? ` — ${r.reason}` : '');
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Remove';
      removeBtn.addEventListener('click', async () => {
        await apiPost(`api/person_recurring_unavailable_delete.php?id=${r.id}`);
        await refreshRecurringPanel(list, person);
        await loadRecurringUnavailability();
        await refreshBookings();
      });
      item.appendChild(label);
      item.appendChild(removeBtn);
      list.appendChild(item);
    }
  }

  function buildAvatarEl(name, extraClass, personId) {
    const el = document.createElement('span');
    el.className = 'avatar-circle' + (extraClass ? ' ' + extraClass : '');
    el.style.backgroundColor = colorForPerson(personId, name);
    el.textContent = initialsForName(name);
    return el;
  }

  function buildUnavailableBadge(dateIso) {
    const unavailable = unavailableEntriesForDate(dateIso);
    if (!unavailable.length) return null;
    const badge = document.createElement('div');
    badge.className = 'day-unavailable-row';
    const names = unavailable.map((u) => {
      const first = u.person_name.split(' ')[0];
      return u.period !== 'all_day' ? `${first} (${UNAVAILABLE_PERIOD_LABELS[u.period]})` : first;
    }).join(', ');
    badge.textContent = `⛔ ${names} unavailable`;
    badge.title = unavailable.map((u) => {
      const periodSuffix = u.period !== 'all_day' ? ` (${UNAVAILABLE_PERIOD_LABELS[u.period]})` : '';
      return `${u.person_name}${periodSuffix}${u.reason ? ': ' + u.reason : ''}`;
    }).join('\n');
    return badge;
  }

  function buildPersonRow(person) {
    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.className = 'name-cell';
    nameCell.appendChild(buildAvatarEl(person.name, 'avatar-sm', person.id));
    nameCell.appendChild(document.createTextNode(person.name));

    const roleCell = document.createElement('td');
    roleCell.textContent = person.role || '';

    const emailCell = document.createElement('td');
    emailCell.textContent = person.email;

    const statusCell = document.createElement('td');
    const pill = document.createElement('span');
    pill.className = `status-pill ${person.active ? 'active' : 'inactive'}`;
    pill.textContent = person.active ? 'Active' : 'Inactive';
    statusCell.appendChild(pill);

    const actionsCell = document.createElement('td');
    actionsCell.className = 'actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.textContent = 'Edit';
    editBtn.addEventListener('click', () => enterEditMode(row, person));

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.textContent = person.active ? 'Deactivate' : 'Reactivate';
    toggleBtn.addEventListener('click', async () => {
      await apiPost(`api/people_deactivate.php?id=${person.id}`, { active: !person.active });
      await loadPeopleTable();
      await loadPeople();
      await loadPersonColors();
    });

    const unavailBtn = document.createElement('button');
    unavailBtn.type = 'button';
    unavailBtn.textContent = 'Unavailable days';
    unavailBtn.addEventListener('click', () => {
      const panelRow = row.nextElementSibling;
      const nowVisible = panelRow.classList.toggle('hidden') === false;
      if (nowVisible) {
        refreshUnavailablePanel(panelRow.querySelector('.unavailable-list'), person);
        refreshRecurringPanel(panelRow.querySelector('.recurring-list'), person);
      }
    });

    actionsCell.appendChild(editBtn);
    actionsCell.appendChild(toggleBtn);
    actionsCell.appendChild(unavailBtn);

    row.appendChild(nameCell);
    row.appendChild(roleCell);
    row.appendChild(emailCell);
    row.appendChild(statusCell);
    row.appendChild(actionsCell);
    return row;
  }

  function enterEditMode(row, person) {
    row.innerHTML = '';

    const nameCell = document.createElement('td');
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.value = person.name;
    nameCell.appendChild(nameInput);

    const roleCell = document.createElement('td');
    const roleInput = document.createElement('input');
    roleInput.type = 'text';
    roleInput.value = person.role || '';
    roleCell.appendChild(roleInput);

    const emailCell = document.createElement('td');
    const emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.value = person.email;
    emailCell.appendChild(emailInput);

    const statusCell = document.createElement('td');
    const pill = document.createElement('span');
    pill.className = `status-pill ${person.active ? 'active' : 'inactive'}`;
    pill.textContent = person.active ? 'Active' : 'Inactive';
    statusCell.appendChild(pill);

    const actionsCell = document.createElement('td');
    actionsCell.className = 'actions';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'primary';
    saveBtn.textContent = 'Save';
    saveBtn.addEventListener('click', async () => {
      try {
        await apiPost(`api/people_update.php?id=${person.id}`, {
          name: nameInput.value.trim(),
          role: roleInput.value.trim(),
          email: emailInput.value.trim(),
        });
        await loadPeopleTable();
        await loadPeople();
        await loadPersonColors();
      } catch (err) {
        alert(err.message);
      }
    });

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', () => loadPeopleTable());

    actionsCell.appendChild(saveBtn);
    actionsCell.appendChild(cancelBtn);

    row.appendChild(nameCell);
    row.appendChild(roleCell);
    row.appendChild(emailCell);
    row.appendChild(statusCell);
    row.appendChild(actionsCell);
  }

  async function onPersonFormSubmit(e) {
    e.preventDefault();
    hidePersonFormError();
    try {
      await apiPost('api/people_create.php', {
        name: el.personName.value.trim(),
        role: el.personRole.value.trim(),
        email: el.personEmail.value.trim(),
      });
      el.personForm.reset();
      await loadPeopleTable();
      await loadPeople();
      await loadPersonColors();
    } catch (err) {
      showPersonFormError(err.message);
    }
  }

  function hideClientFormError() {
    el.clientFormError.classList.add('hidden');
  }

  function showClientFormError(message) {
    el.clientFormError.textContent = message;
    el.clientFormError.classList.remove('hidden');
  }

  async function loadClientsTable() {
    const clients = await apiGet('api/clients_list.php?include_inactive=1');
    el.clientsTableBody.innerHTML = '';
    for (const client of clients) {
      el.clientsTableBody.appendChild(buildClientRow(client));
    }
  }

  function buildClientLogoEl(client) {
    if (client.logo_path) {
      const img = document.createElement('img');
      img.className = 'client-logo-thumb';
      img.src = client.logo_path;
      img.alt = client.name;
      return img;
    }
    const placeholder = document.createElement('span');
    placeholder.className = 'client-logo-placeholder';
    placeholder.textContent = 'No logo';
    return placeholder;
  }

  function buildClientRow(client) {
    const row = document.createElement('tr');

    const logoCell = document.createElement('td');
    logoCell.appendChild(buildClientLogoEl(client));

    const nameCell = document.createElement('td');
    nameCell.textContent = client.name;

    const statusCell = document.createElement('td');
    const pill = document.createElement('span');
    pill.className = `status-pill ${client.active ? 'active' : 'inactive'}`;
    pill.textContent = client.active ? 'Active' : 'Inactive';
    statusCell.appendChild(pill);

    const actionsCell = document.createElement('td');
    actionsCell.className = 'actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.textContent = 'Edit';
    editBtn.addEventListener('click', () => enterClientEditMode(row, client));

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.textContent = client.active ? 'Deactivate' : 'Reactivate';
    toggleBtn.addEventListener('click', async () => {
      await apiPost(`api/clients_deactivate.php?id=${client.id}`, { active: !client.active });
      await loadClientsTable();
      await loadClients();
    });

    actionsCell.appendChild(editBtn);
    actionsCell.appendChild(toggleBtn);

    row.appendChild(logoCell);
    row.appendChild(nameCell);
    row.appendChild(statusCell);
    row.appendChild(actionsCell);
    return row;
  }

  function enterClientEditMode(row, client) {
    row.innerHTML = '';

    const logoCell = document.createElement('td');
    logoCell.appendChild(buildClientLogoEl(client));
    const logoInput = document.createElement('input');
    logoInput.type = 'file';
    logoInput.accept = 'image/png,image/jpeg,image/gif,image/webp';
    logoInput.style.marginTop = '4px';
    logoInput.style.width = '110px';
    logoCell.appendChild(logoInput);

    const nameCell = document.createElement('td');
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.value = client.name;
    nameCell.appendChild(nameInput);

    const statusCell = document.createElement('td');
    const pill = document.createElement('span');
    pill.className = `status-pill ${client.active ? 'active' : 'inactive'}`;
    pill.textContent = client.active ? 'Active' : 'Inactive';
    statusCell.appendChild(pill);

    const actionsCell = document.createElement('td');
    actionsCell.className = 'actions';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'primary';
    saveBtn.textContent = 'Save';
    saveBtn.addEventListener('click', async () => {
      try {
        const formData = new FormData();
        formData.append('name', nameInput.value.trim());
        if (logoInput.files[0]) formData.append('logo', logoInput.files[0]);
        await apiPostForm(`api/clients_update.php?id=${client.id}`, formData);
        await loadClientsTable();
        await loadClients();
      } catch (err) {
        alert(err.message);
      }
    });

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', () => loadClientsTable());

    actionsCell.appendChild(saveBtn);
    actionsCell.appendChild(cancelBtn);

    row.appendChild(logoCell);
    row.appendChild(nameCell);
    row.appendChild(statusCell);
    row.appendChild(actionsCell);
  }

  async function onClientFormSubmit(e) {
    e.preventDefault();
    hideClientFormError();
    try {
      const formData = new FormData();
      formData.append('name', el.clientName.value.trim());
      if (el.clientLogo.files[0]) formData.append('logo', el.clientLogo.files[0]);
      await apiPostForm('api/clients_create.php', formData);
      el.clientForm.reset();
      await loadClientsTable();
      await loadClients();
    } catch (err) {
      showClientFormError(err.message);
    }
  }

  el.viewWeekBtn.addEventListener('click', () => switchView('week'));
  el.viewMonthBtn.addEventListener('click', () => switchView('month'));
  el.prevMonth.addEventListener('click', () => shiftMonth(-1));
  el.nextMonth.addEventListener('click', () => shiftMonth(1));
  el.thisMonthBtn.addEventListener('click', () => {
    state.monthStr = currentMonthStr(new Date());
    loadMonth();
  });
  el.filterPerson.addEventListener('change', onFilterChange);
  el.filterClient.addEventListener('change', onFilterChange);
  el.filterKit.addEventListener('change', onFilterChange);
  el.clearFiltersBtn.addEventListener('click', clearFilters);
  el.viewPeopleBtn.addEventListener('click', () => switchView('people'));
  el.viewAdminBtn.addEventListener('click', () => switchView('admin'));
  el.adminTabClientsBtn.addEventListener('click', () => switchAdminSubView('clients'));
  el.adminTabKitUsageBtn.addEventListener('click', () => switchAdminSubView('kitUsage'));
  el.adminTabBlockedDaysBtn.addEventListener('click', () => switchAdminSubView('blockedDays'));
  el.adminTabNeedsPrepBtn.addEventListener('click', () => switchAdminSubView('needsPrep'));
  el.kitUsagePrevMonth.addEventListener('click', () => shiftKitUsagePageMonth(-1));
  el.kitUsageNextMonth.addEventListener('click', () => shiftKitUsagePageMonth(1));
  el.kitUsageThisMonth.addEventListener('click', () => {
    state.kitUsagePageMonth = currentMonthStr(new Date());
    loadKitUsagePage();
  });
  el.blockedDayForm.addEventListener('submit', onBlockedDayFormSubmit);
  el.clientForm.addEventListener('submit', onClientFormSubmit);
  el.fieldLocation.addEventListener('input', updateLocationMapsLink);
  el.fieldWhat3Words.addEventListener('input', updateWhat3WordsLink);
  el.fieldDate.addEventListener('input', scheduleConflictCheck);
  el.fieldStart.addEventListener('input', scheduleConflictCheck);
  el.fieldEnd.addEventListener('input', scheduleConflictCheck);
  el.fieldAttendees.addEventListener('change', scheduleConflictCheck);
  for (const item of CHECKLIST_ITEMS) {
    el['fieldChecklist' + item.elKey + 'Url'].addEventListener('input', () => updateChecklistUrlLink(item));
  }
  el.fieldChecklistShotListNa.addEventListener('change', updateShotListNaState);
  el.openAllDocsBtn.addEventListener('click', openAllAttachedDocuments);
  el.personForm.addEventListener('submit', onPersonFormSubmit);

  el.prevWeek.addEventListener('click', () => { state.weekStart = addDays(state.weekStart, -7); loadWeek(); });
  el.nextWeek.addEventListener('click', () => { state.weekStart = addDays(state.weekStart, 7); loadWeek(); });
  el.todayBtn.addEventListener('click', () => { state.weekStart = mondayOf(new Date()); loadWeek(); });
  el.addBookingBtn.addEventListener('click', () => openAddModal(new Date()));
  el.downloadWeekBtn.addEventListener('click', () => window.print());
  el.cancelModalBtn.addEventListener('click', closeModal);
  el.modalBackdrop.addEventListener('click', (e) => { if (e.target === el.modalBackdrop) closeModal(); });
  el.bookingForm.addEventListener('submit', onFormSubmit);
  el.deleteBookingBtn.addEventListener('click', onDeleteClick);
  el.confirmBookingBtn.addEventListener('click', onConfirmClick);
  el.unconfirmBookingBtn.addEventListener('click', onUnconfirmClick);
  el.emailConfirmationBtn.addEventListener('click', onEmailConfirmationClick);
  el.cancelEmailPreviewBtn.addEventListener('click', closeEmailPreview);
  el.sendEmailPreviewBtn.addEventListener('click', onSendEmailPreviewClick);
  el.emailPreviewBackdrop.addEventListener('click', (e) => { if (e.target === el.emailPreviewBackdrop) closeEmailPreview(); });

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
    await loadPeople();
    await loadPersonColors();
    await loadClients();
    await loadBlockedDays();
    await loadUnavailability();
    await loadRecurringUnavailability();
    await loadWeek();
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
