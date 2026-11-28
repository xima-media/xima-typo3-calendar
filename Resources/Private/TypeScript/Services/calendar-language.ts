type Typo3LanguageWindow = Window & {
    TYPO3?: {
        lang?: Record<string, string>;
    };
};

export const getCalendarLanguageLabel = (key: string): string => (
    (window as unknown as Typo3LanguageWindow).TYPO3?.lang?.[key] || key
);
