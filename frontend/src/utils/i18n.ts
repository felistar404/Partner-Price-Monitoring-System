import translations from '../i18n';

export function useTranslations(locale: string) {
  return function t(key: string, defaultValue: string = '') {
    const keys = key.split('.');
    let value = translations[locale] || {};
    
    for (const k of keys) {
      if (!value || !value[k]) return defaultValue || key;
      value = value[k];
    }
    
    return value || defaultValue || key;
  };
}