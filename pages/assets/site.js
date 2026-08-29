(function () {
  'use strict';

  const body = document.body;
  const menuButton = document.querySelector('[data-menu-toggle]');
  const navigation = document.querySelector('[data-site-nav]');
  const themeButton = document.querySelector('[data-doc-theme-toggle]');
  const liveRegion = document.querySelector('[data-live-region]');

  function announce(message) {
    if (!liveRegion) return;
    liveRegion.textContent = '';
    window.setTimeout(function () {
      liveRegion.textContent = message;
    }, 20);
  }

  if (menuButton && navigation) {
    menuButton.addEventListener('click', function () {
      const open = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(open));
    });

    navigation.addEventListener('click', function (event) {
      if (!event.target.closest('a')) return;
      navigation.classList.remove('is-open');
      menuButton.setAttribute('aria-expanded', 'false');
    });
  }

  if (themeButton) {
    themeButton.addEventListener('click', function () {
      const next = body.dataset.docTheme === 'light' ? 'dark' : 'light';
      body.dataset.docTheme = next;
      themeButton.setAttribute('aria-label', next === 'light' ? 'Use dark documentation theme' : 'Use light documentation theme');
      themeButton.textContent = next === 'light' ? '☾' : '☀';
      announce('Documentation theme changed to ' + next + '.');
    });
  }

  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const target = document.querySelector(button.dataset.copyTarget);
      if (!target) return;
      const text = target.textContent.trim();

      try {
        await navigator.clipboard.writeText(text);
      } catch (error) {
        const helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();
        document.execCommand('copy');
        helper.remove();
      }

      const original = button.textContent;
      button.textContent = 'Copied';
      announce('Command copied to clipboard.');
      window.setTimeout(function () {
        button.textContent = original;
      }, 1500);
    });
  });

  function shellQuote(value) {
    const clean = String(value || '').trim();
    if (!clean) return '';
    return "'" + clean.replace(/'/g, "'\\''") + "'";
  }

  const commandBuilder = document.querySelector('[data-command-builder]');
  if (commandBuilder) {
    const rootInput = commandBuilder.querySelector('[data-command-root]');
    const stateInput = commandBuilder.querySelector('[data-command-state]');
    const modeButtons = commandBuilder.querySelectorAll('[data-command-mode]');
    const output = commandBuilder.querySelector('[data-command-output]');
    let mode = 'install';

    function updateCommand() {
      const root = shellQuote(rootInput.value || '/var/www/itflow.example.com');
      const stateValue = shellQuote(stateInput.value);
      const state = stateValue ? ' \\\n  --state-root ' + stateValue : '';
      let command;

      if (mode === 'install') {
        command = 'curl --fail --location --output install-latest.sh \\\n  https://raw.githubusercontent.com/ithealthtech/nexus-theme-manager-for-itflow/main/install-latest.sh\nchmod +x install-latest.sh\nsudo ./install-latest.sh --root ' + root + state;
      } else if (mode === 'update') {
        command = 'cd /tmp\ncurl --fail --location --output install-latest.sh \\\n  https://raw.githubusercontent.com/ithealthtech/nexus-theme-manager-for-itflow/main/install-latest.sh\nchmod +x install-latest.sh\nsudo ./install-latest.sh --root ' + root + state;
      } else if (mode === 'verify') {
        command = 'sudo php manager.php status --root ' + root + state + '\nsudo php manager.php verify --root ' + root + state;
      } else {
        command = 'sudo php manager.php disable --root ' + root + state + ' --yes';
      }

      output.textContent = command;
    }

    modeButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        mode = button.dataset.commandMode;
        modeButtons.forEach(function (candidate) {
          candidate.setAttribute('aria-pressed', String(candidate === button));
        });
        updateCommand();
      });
    });

    rootInput.addEventListener('input', updateCommand);
    stateInput.addEventListener('input', updateCommand);
    updateCommand();
  }

  document.querySelectorAll('[data-filter-list]').forEach(function (container) {
    const input = container.querySelector('[data-filter-input]');
    const items = Array.from(container.querySelectorAll('[data-filter-item]'));
    const empty = container.querySelector('[data-filter-empty]');
    if (!input) return;

    input.addEventListener('input', function () {
      const query = input.value.trim().toLowerCase();
      let visible = 0;
      items.forEach(function (item) {
        const match = !query || item.textContent.toLowerCase().includes(query);
        item.hidden = !match;
        if (match) visible += 1;
      });
      if (empty) empty.style.display = visible ? 'none' : 'block';
    });
  });

  document.querySelectorAll('[data-current-year]').forEach(function (element) {
    element.textContent = String(new Date().getFullYear());
  });
})();
