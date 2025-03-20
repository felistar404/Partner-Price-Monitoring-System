export default {
  defaultLocale: "en",
  locales: [
    {
      code: "en",
      name: "English",
    },
    {
      code: "zh-hant",
      name: "繁體中文",
    },
    {
      code: "zh-hans",
      name: "简体中文",
    },
  ],
  routes: {
    // Define your route translations here (if needed)
    // "/about": {
    //   "zh-hant": "/關於",
    //   "zh-hans": "/关于"
    // }
  },
  // Clean URLs without the locale prefix for default locale 
  showDefaultLocale: false,
};