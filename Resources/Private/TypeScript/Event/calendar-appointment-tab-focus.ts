const APPOINTMENTS_FIELD_NAME = 'appointments';

const findAppointmentsTabElement = (): HTMLElement | null => [...document.querySelectorAll<HTMLElement>('[role="tab"], .nav-link')]
    .find((tab) => {
        const panelId = tab.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        return panel !== null && panel.querySelector(`[data-local-field="${APPOINTMENTS_FIELD_NAME}"]`) !== null;
    }) ?? null;

const activateAppointmentsTab = (): boolean => {
    const appointmentsTabElement = findAppointmentsTabElement();
    if (!appointmentsTabElement) {
        return false;
    }

    if (!appointmentsTabElement.classList.contains('active')) {
        appointmentsTabElement.click();
    }

    return appointmentsTabElement.classList.contains('active');
};

if (!activateAppointmentsTab()) {
    const retryTimer = window.setInterval(() => {
        if (activateAppointmentsTab()) {
            window.clearInterval(retryTimer);
        }
    }, 100);
    window.setTimeout(() => window.clearInterval(retryTimer), 5000);
}
