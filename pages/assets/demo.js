(function () {
  'use strict';

  const frame = document.querySelector('[data-demo-frame]');
  if (!frame) return;

  const palettes = {
    aurora: { accent: '#69bff5', accent2: '#7888ff', shell: '#121124' },
    ocean: { accent: '#38bdf8', accent2: '#2563eb', shell: '#0f172a' },
    emerald: { accent: '#5ee9b5', accent2: '#20b486', shell: '#10201c' },
    ember: { accent: '#ffb45e', accent2: '#f36d75', shell: '#211316' },
    slate: { accent: '#a5b4fc', accent2: '#64748b', shell: '#172033' }
  };

  const tickets = [
    { id: 'T-1842', subject: 'VPN disconnects after sleep', client: 'Northwind Clinic', priority: 'High', status: 'Waiting on us', updated: '12 min' },
    { id: 'T-1839', subject: 'New employee onboarding', client: 'Summit Design Co.', priority: 'Normal', status: 'In progress', updated: '28 min' },
    { id: 'T-1836', subject: 'Printer queue unavailable', client: 'Harbor Accounting', priority: 'Normal', status: 'Scheduled', updated: '1 hr' },
    { id: 'T-1828', subject: 'Shared mailbox permissions', client: 'Maple Street Legal', priority: 'Low', status: 'Waiting on client', updated: 'Yesterday' }
  ];

  const viewButtons = document.querySelectorAll('[data-demo-view-button]');
  const views = document.querySelectorAll('[data-demo-view]');
  const paletteButtons = document.querySelectorAll('[data-demo-palette]');
  const modeButtons = document.querySelectorAll('[data-demo-mode]');
  const ticketBody = document.querySelector('[data-demo-ticket-body]');
  const ticketSearch = document.querySelector('[data-demo-ticket-search]');
  const ticketStatus = document.querySelector('[data-demo-ticket-status]');
  const density = document.querySelector('[data-demo-density]');
  const corners = document.querySelector('[data-demo-corners]');
  const modal = document.querySelector('[data-demo-modal]');
  const toast = document.querySelector('[data-demo-toast]');

  function announce(message) {
    const region = document.querySelector('[data-demo-live]');
    if (!region) return;
    region.textContent = '';
    window.setTimeout(function () { region.textContent = message; }, 20);
  }

  function setView(name) {
    viewButtons.forEach(function (button) {
      const active = button.dataset.demoViewButton === name;
      button.setAttribute('aria-pressed', String(active));
    });
    views.forEach(function (view) {
      view.classList.toggle('is-active', view.dataset.demoView === name);
    });
    announce(name.replace('-', ' ') + ' simulation selected.');
  }

  viewButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      setView(button.dataset.demoViewButton);
    });
  });

  paletteButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const palette = palettes[button.dataset.demoPalette];
      frame.style.setProperty('--demo-accent', palette.accent);
      frame.style.setProperty('--demo-accent-2', palette.accent2);
      frame.style.setProperty('--demo-shell', palette.shell);
      paletteButtons.forEach(function (candidate) {
        candidate.setAttribute('aria-pressed', String(candidate === button));
      });
      announce(button.getAttribute('aria-label') + ' palette applied to the simulation.');
    });
  });

  modeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const mode = button.dataset.demoMode;
      frame.dataset.mode = mode;
      modeButtons.forEach(function (candidate) {
        candidate.setAttribute('aria-pressed', String(candidate === button));
      });
      announce(mode + ' mode applied to the simulation.');
    });
  });

  function renderTickets() {
    const query = (ticketSearch ? ticketSearch.value : '').trim().toLowerCase();
    const status = ticketStatus ? ticketStatus.value : 'all';
    const matches = tickets.filter(function (ticket) {
      const text = Object.values(ticket).join(' ').toLowerCase();
      return (!query || text.includes(query)) && (status === 'all' || ticket.status === status);
    });

    ticketBody.textContent = '';
    matches.forEach(function (ticket) {
      const row = document.createElement('tr');
      row.innerHTML = '<td><strong>' + ticket.id + '</strong></td>' +
        '<td><strong>' + ticket.subject + '</strong><br><small>' + ticket.client + '</small></td>' +
        '<td>' + ticket.priority + '</td>' +
        '<td><span class="demo-status">' + ticket.status + '</span></td>' +
        '<td>' + ticket.updated + '</td>';
      row.addEventListener('click', function () {
        showToast(ticket.id + ' opened in the local simulation.');
      });
      ticketBody.appendChild(row);
    });

    if (!matches.length) {
      const row = document.createElement('tr');
      row.innerHTML = '<td colspan="5">No fictional tickets match these filters.</td>';
      ticketBody.appendChild(row);
    }
  }

  if (ticketSearch) ticketSearch.addEventListener('input', renderTickets);
  if (ticketStatus) ticketStatus.addEventListener('change', renderTickets);
  renderTickets();

  if (density) {
    density.addEventListener('change', function () {
      const padding = density.value === 'compact' ? '8px 12px' : density.value === 'spacious' ? '15px 18px' : '11px 14px';
      frame.querySelectorAll('.demo-table th, .demo-table td').forEach(function (cell) {
        cell.style.padding = padding;
      });
      announce(density.value + ' content density applied.');
    });
  }

  if (corners) {
    corners.addEventListener('change', function () {
      const radius = corners.value === 'sharp' ? '4px' : corners.value === 'rounded' ? '24px' : '14px';
      frame.style.setProperty('--demo-radius', radius);
      announce(corners.value + ' corner style applied.');
    });
  }

  document.querySelectorAll('[data-open-demo-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      modal.showModal();
    });
  });

  document.querySelectorAll('[data-close-demo-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      modal.close();
    });
  });

  const demoForm = document.querySelector('[data-demo-form]');
  if (demoForm) {
    demoForm.addEventListener('submit', function (event) {
      event.preventDefault();
      modal.close();
      demoForm.reset();
      showToast('Simulation complete. Nothing was saved or sent.');
    });
  }

  document.querySelectorAll('[data-demo-action]').forEach(function (button) {
    button.addEventListener('click', function () {
      showToast(button.dataset.demoAction + ' simulated locally. No changes were saved.');
    });
  });

  function showToast(message) {
    toast.textContent = message;
    toast.classList.add('is-visible');
    announce(message);
    window.setTimeout(function () {
      toast.classList.remove('is-visible');
    }, 2600);
  }
})();
