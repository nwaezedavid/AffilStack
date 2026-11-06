/**
 * Adds what can't live in app.json. GOOGLE_SERVICES_JSON is a "File"
 * environment variable on the EAS project (expo.dev → Project → Environment
 * variables) holding Firebase's google-services.json, which Android needs for
 * push notifications. It isn't committed; builds without it still work, just
 * without Android push.
 */
module.exports = ({ config }) => ({
  ...config,
  android: {
    ...config.android,
    ...(process.env.GOOGLE_SERVICES_JSON ? { googleServicesFile: process.env.GOOGLE_SERVICES_JSON } : {}),
  },
});
