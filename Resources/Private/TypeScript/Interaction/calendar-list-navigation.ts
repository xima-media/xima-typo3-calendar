type CalendarListNavigationLabels = {
    previous: string;
    next: string;
};

export type CalendarListNavigation = {
    viewChanged(viewType: string, currentStart: Date): void;
    destroy(): void;
};

export const createCalendarListNavigation = (
    container: HTMLElement,
    labels: CalendarListNavigationLabels,
    locale: string,
    onPrevious: () => void,
    onNext: () => void,
): CalendarListNavigation => {
    const navigation = document.createElement('nav');
    navigation.className = 'xima-calendar-list-navigation';
    navigation.setAttribute('aria-label', labels.previous + ' / ' + labels.next);

    const createButton = (iconName: string, onClick: () => void): HTMLButtonElement => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ec-button btn btn-default xima-calendar-list-navigation__button';
        if (iconName === 'ec-next') {
            button.classList.add('xima-calendar-list-navigation__button--next');
        }
        const icon = document.createElement('span');
        icon.className = `ec-icon ${iconName}`;
        icon.setAttribute('aria-hidden', 'true');
        const text = document.createElement('span');
        text.className = 'xima-calendar-list-navigation__label';
        button.append(icon, text);
        button.addEventListener('click', onClick);
        return button;
    };

    const previousButton = createButton('ec-prev', onPrevious);
    const nextButton = createButton('ec-next', onNext);
    const previousLabel = previousButton.querySelector<HTMLElement>('.xima-calendar-list-navigation__label');
    const nextLabel = nextButton.querySelector<HTMLElement>('.xima-calendar-list-navigation__label');
    navigation.append(previousButton, nextButton);

    const monthFormatter = new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
    });

    const updateMonthLabels = (currentStart: Date): void => {
        const previousMonth = new Date(currentStart.getFullYear(), currentStart.getMonth() - 1, 1);
        const nextMonth = new Date(currentStart.getFullYear(), currentStart.getMonth() + 1, 1);
        const previousMonthLabel = monthFormatter.format(previousMonth);
        const nextMonthLabel = monthFormatter.format(nextMonth);

        if (previousLabel) {
            previousLabel.textContent = previousMonthLabel;
        }
        if (nextLabel) {
            nextLabel.textContent = nextMonthLabel;
        }
        previousButton.setAttribute('aria-label', `${labels.previous}: ${previousMonthLabel}`);
        previousButton.title = `${labels.previous}: ${previousMonthLabel}`;
        nextButton.setAttribute('aria-label', `${labels.next}: ${nextMonthLabel}`);
        nextButton.title = `${labels.next}: ${nextMonthLabel}`;
    };

    let mountFrame: number | null = null;

    const cancelMount = (): void => {
        if (mountFrame !== null) {
            window.cancelAnimationFrame(mountFrame);
            mountFrame = null;
        }
    };

    // datesSet may fire before the list view has replaced the previous view's DOM.
    const mountAfterRender = (): void => {
        cancelMount();
        mountFrame = window.requestAnimationFrame(() => {
            mountFrame = null;
            const listMain = container.querySelector<HTMLElement>('.ec-list .ec-main');
            if (listMain && navigation.parentElement !== listMain) {
                listMain.append(navigation);
            }
        });
    };

    return {
        viewChanged(viewType, currentStart): void {
            const isList = viewType.startsWith('list');
            updateMonthLabels(currentStart);
            if (isList) {
                mountAfterRender();
            } else {
                cancelMount();
                navigation.remove();
            }
        },

        destroy(): void {
            cancelMount();
            previousButton.removeEventListener('click', onPrevious);
            nextButton.removeEventListener('click', onNext);
            navigation.remove();
        },
    };
};
