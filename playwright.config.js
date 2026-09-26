// Tests navigateur de NEWGEN (portés le 26/09/2026 depuis quatre scripts
// maison, sur le modèle de NextStep) : un seul lancement, un navigateur,
// tests en parallèle. Lancer : npx playwright test
const { defineConfig } = require('@playwright/test');
const fs = require('fs');

const PREINSTALLED = '/opt/pw-browsers/chromium';

module.exports = defineConfig({
  testDir: './e2e',
  // Les scénarios réseau attendent de vrais délais (jusqu'à ~26 s) : ils
  // tournent chacun dans leur page, en parallèle, plutôt que l'un après l'autre.
  fullyParallel: true,
  workers: process.env.CI ? 4 : undefined,
  timeout: 60000,
  reporter: process.env.CI ? 'line' : 'list',
  use: {
    baseURL: 'http://127.0.0.1:7475',
    launchOptions: {
      executablePath: fs.existsSync(PREINSTALLED) ? PREINSTALLED : undefined,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
  },
  webServer: {
    command: 'node e2e/server.js',
    url: 'http://127.0.0.1:7475/index.html',
    reuseExistingServer: false,
    timeout: 10000,
  },
});
