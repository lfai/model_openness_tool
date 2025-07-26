# Automated Tests

These are **[Playwright](https://playwright.dev) end-to-end tests**, intended to aid regression testing by verifying basic user workflows.

---

## Prerequisites

- Node.js (version 16 or higher recommended)
- npm (or yarn) package manager

---

## Setup

Inside this folder, install the Javascript dependencies for the Playwright tests
```
npm install
```
These tests run on headless Chromium binaries. To use additional browsers, you can uncomment the relevant sections of `./playwright.config.js`.

## Running the Tests
Run all tests with:
```
npx playwright test
```
Run the tests in headed mode (see the browser window during tests):
```
npx playwright test --headed
```
After testing, you can view the test report in a browser by running:
```
npx playwright show-report
```