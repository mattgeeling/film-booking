(() => {
  const GRID_START_HOUR = 7;
  const GRID_END_HOUR = 19;
  const PX_PER_HOUR = 60;
  const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
  const STATUS_LABELS = { pencil: '✎ Pencil', confirmed: '✓ Confirmed' };
  const GOOGLE_CLIENT_ID = '1020593076419-tv5l93390dog560otm0o2p87h36vq1rs.apps.googleusercontent.com';

  const state = {
    weekStart: mondayOf(new Date()),
    bookings: [],
    people: [],
    editingId: null,
  };

  const el = {
    weekGrid: document.getElementById('weekGrid'),
    weekLabel: document.getElementById('weekLabel'),
    weekTally: document.getElementById('weekTally'),
    peopleSummaryList: document.getElementById('peopleSummaryList'),
    prevWeek: document.getElementById('prevWeek'),
    nextWeek: document.getElementById('nextWeek'),
    todayBtn: document.getElementById('todayBtn'),
    addBookingBtn: document.getElementById('addBookingBtn'),
    modalBackdrop: document.getElementById('modalBackdrop'),
    bookingForm: document.getElementById('bookingForm'),
    modalTitle: document.getElementById('modalTitle'),
    fieldTitle: document.getElementById('fieldTitle'),
    fieldDate: document.getElementById('fieldDate'),
    fieldStart: document.getElementById('fieldStart'),
    fieldEnd: document.getElementById('fieldEnd'),
    fieldLocation: document.getElementById('fieldLocation'),
    fieldAttendees: document.getElementById('fieldAttendees'),
    fieldNotes: document.getElementById('fieldNotes'),
    formError: document.getElementById('formError'),
    cancelModalBtn: document.getElementById('cancelModalBtn'),
    deleteBookingBtn: document.getElementById('deleteBookingBtn'),
    confirmBookingBtn: document.getElementById('confirmBookingBtn'),
    syncResults: document.getElementById('syncResults'),
    viewWeekBtn: document.getElementById('viewWeekBtn'),
    viewPeopleBtn: document.getElementById('viewPeopleBtn'),
    weekControls: document.getElementById('weekControls'),
    weekLegend: document.getElementById('weekLegend'),
    peopleView: document.getElementById('peopleView'),
    personForm: document.getElementById('personForm'),
    personName: document.getElementById('personName'),
    personEmail: document.getElementById('personEmail'),
    personFormError: document.getElementById('personFormError'),
    peopleTableBody: document.getElementById('peopleTableBody'),
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

  function buildWeekSkeleton() {
    el.weekGrid.innerHTML = '';

    const gutterHeader = document.createElement('div');
    gutterHeader.className = 'time-gutter-header';
    el.weekGrid.appendChild(gutterHeader);

    for (let i = 0; i < 5; i++) {
      const d = addDays(state.weekStart, i);
      const header = document.createElement('div');
      header.className = 'day-header';
      header.innerHTML = `${DAY_NAMES[i]}<span class="day-date">${d.getDate()}</span>`;
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
      col.className = 'day-column';
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

  function renderBookings() {
    const columns = el.weekGrid.querySelectorAll('.day-column');
    columns.forEach((c) => { c.querySelectorAll('.booking-block').forEach((b) => b.remove()); });

    for (const booking of state.bookings) {
      const start = new Date(booking.start_datetime.replace(' ', 'T'));
      const end = new Date(booking.end_datetime.replace(' ', 'T'));
      const dayIndex = dayIndexFor(start);
      if (dayIndex === null) continue;

      const top = timeToOffsetPx(start);
      const bottom = timeToOffsetPx(end);
      const height = Math.max(bottom - top, 20);

      const block = document.createElement('div');
      block.className = `booking-block status-${booking.status}`;
      block.style.top = `${top}px`;
      block.style.height = `${height}px`;

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

      block.appendChild(statusEl);
      block.appendChild(titleEl);
      block.appendChild(metaEl);
      block.addEventListener('click', (e) => {
        e.stopPropagation();
        openEditModal(booking);
      });

      columns[dayIndex].appendChild(block);
    }
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
    el.weekLabel.textContent = `${fmt(state.weekStart)} – ${fmt(end)}`;
  }

  function updateWeekTally() {
    const total = state.bookings.length;
    const confirmed = state.bookings.filter((b) => b.status === 'confirmed').length;
    const pencil = total - confirmed;
    if (total === 0) {
      el.weekTally.textContent = 'No bookings';
    } else {
      el.weekTally.textContent = `${total} booking${total === 1 ? '' : 's'} · ${pencil} pencil · ${confirmed} confirmed`;
    }
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

      const name = document.createElement('span');
      name.className = 'psr-name';
      name.textContent = person.name;

      const badge = document.createElement('span');
      badge.className = 'psr-count' + (count === 0 ? ' zero' : '');
      badge.textContent = String(count);

      row.appendChild(name);
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
  }

  async function loadPeople() {
    state.people = await apiGet('api/people_list.php');
    el.fieldAttendees.innerHTML = '';
    for (const p of state.people) {
      const opt = document.createElement('option');
      opt.value = String(p.id);
      opt.textContent = p.name;
      el.fieldAttendees.appendChild(opt);
    }
    if (state.bookings.length || state.people.length) updateWeekPeopleSummary();
  }

  function onDayColumnClick(dayIndex) {
    openAddModal(addDays(state.weekStart, dayIndex));
  }

  function openAddModal(date) {
    state.editingId = null;
    el.modalTitle.textContent = 'New booking';
    el.bookingForm.reset();
    el.fieldDate.value = isoDate(date);
    el.fieldStart.value = '09:00';
    el.fieldEnd.value = '17:00';
    el.deleteBookingBtn.classList.add('hidden');
    el.confirmBookingBtn.classList.add('hidden');
    el.formError.classList.add('hidden');
    el.syncResults.classList.add('hidden');
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
    el.fieldNotes.value = booking.notes || '';
    const attendeeIds = new Set(booking.attendees.map((a) => a.id));
    for (const opt of el.fieldAttendees.options) {
      opt.selected = attendeeIds.has(Number(opt.value));
    }
    el.deleteBookingBtn.classList.remove('hidden');
    el.deleteBookingBtn.textContent = booking.status === 'confirmed' ? 'Cancel booking' : 'Delete';
    el.confirmBookingBtn.classList.toggle('hidden', booking.status !== 'pencil');
    el.formError.classList.add('hidden');
    el.syncResults.classList.add('hidden');
    el.modalBackdrop.classList.remove('hidden');
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
      notes: el.fieldNotes.value.trim(),
      start_datetime: `${el.fieldDate.value}T${el.fieldStart.value}`,
      end_datetime: `${el.fieldDate.value}T${el.fieldEnd.value}`,
      attendee_ids: attendeeIds,
    };

    try {
      let data;
      if (state.editingId) {
        data = await apiPost(`api/bookings_update.php?id=${state.editingId}`, payload);
      } else {
        data = await apiPost('api/bookings_create.php', payload);
      }
      closeModal();
      await loadWeek();
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
      await loadWeek();
      const updated = state.bookings.find((b) => b.id === state.editingId);
      if (updated) openEditModal(updated);
      showSyncResults(data.results);
    } catch (err) {
      showFormError(err.message);
    } finally {
      el.confirmBookingBtn.disabled = false;
    }
  }

  async function onDeleteClick() {
    if (!state.editingId) return;
    if (!confirm('Remove this booking?')) return;
    try {
      await apiPost(`api/bookings_cancel.php?id=${state.editingId}`);
      closeModal();
      await loadWeek();
    } catch (err) {
      showFormError(err.message);
    }
  }

  function switchView(view) {
    const isWeek = view === 'week';
    el.viewWeekBtn.classList.toggle('active', isWeek);
    el.viewPeopleBtn.classList.toggle('active', !isWeek);
    el.weekGrid.classList.toggle('hidden', !isWeek);
    el.weekControls.classList.toggle('hidden', !isWeek);
    el.weekLegend.classList.toggle('hidden', !isWeek);
    el.addBookingBtn.classList.toggle('hidden', !isWeek);
    el.peopleView.classList.toggle('hidden', isWeek);
    if (!isWeek) loadPeopleTable();
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
    }
  }

  function buildPersonRow(person) {
    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.textContent = person.name;

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
    });

    actionsCell.appendChild(editBtn);
    actionsCell.appendChild(toggleBtn);

    row.appendChild(nameCell);
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
          email: emailInput.value.trim(),
        });
        await loadPeopleTable();
        await loadPeople();
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
        email: el.personEmail.value.trim(),
      });
      el.personForm.reset();
      await loadPeopleTable();
      await loadPeople();
    } catch (err) {
      showPersonFormError(err.message);
    }
  }

  el.viewWeekBtn.addEventListener('click', () => switchView('week'));
  el.viewPeopleBtn.addEventListener('click', () => switchView('people'));
  el.personForm.addEventListener('submit', onPersonFormSubmit);

  el.prevWeek.addEventListener('click', () => { state.weekStart = addDays(state.weekStart, -7); loadWeek(); });
  el.nextWeek.addEventListener('click', () => { state.weekStart = addDays(state.weekStart, 7); loadWeek(); });
  el.todayBtn.addEventListener('click', () => { state.weekStart = mondayOf(new Date()); loadWeek(); });
  el.addBookingBtn.addEventListener('click', () => openAddModal(new Date()));
  el.cancelModalBtn.addEventListener('click', closeModal);
  el.modalBackdrop.addEventListener('click', (e) => { if (e.target === el.modalBackdrop) closeModal(); });
  el.bookingForm.addEventListener('submit', onFormSubmit);
  el.deleteBookingBtn.addEventListener('click', onDeleteClick);
  el.confirmBookingBtn.addEventListener('click', onConfirmClick);

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
