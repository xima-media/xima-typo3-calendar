type Typo3LanguageWindow = Window & {
    TYPO3?: {
        lang?: Record<string, string>;
        settings?: {
            ximaCalendar?: {
                languageLabels?: Record<string, string>;
            };
        };
    };
};

export const getCalendarLanguageLabel = (key: string): string => {
    const currentWindow = window as unknown as Typo3LanguageWindow;
    const topWindow = window.top as unknown as Typo3LanguageWindow;
    const translationWindows: Typo3LanguageWindow[] = [currentWindow, topWindow];

    for (const iframe of Array.from(topWindow.document.querySelectorAll('iframe'))) {
        if (iframe.contentWindow !== null) {
            translationWindows.push(iframe.contentWindow as unknown as Typo3LanguageWindow);
        }
    }

    for (const translationWindow of translationWindows) {
        const label = translationWindow.TYPO3?.lang?.[key]
            ?? translationWindow.TYPO3?.settings?.ximaCalendar?.languageLabels?.[key];

        if (label) {
            return label;
        }
    }

    return '';
};
