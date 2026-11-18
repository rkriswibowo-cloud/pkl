const dashboardLayout = document.getElementById('dashboardLayout');
const dashboardSidebar = document.getElementById('dashboardSidebar');
const sidebarToggle = document.getElementById('dashboardSidebarToggle');
const sidebarClose = document.getElementById('dashboardSidebarClose');
const sidebarCollapse = document.getElementById('dashboardSidebarCollapse');
const sidebarBackdrop = document.getElementById('dashboardSidebarBackdrop');
const sidebarSearch = document.getElementById('dashboardMenuSearch');
const menuEmpty = document.getElementById('dashboardMenuEmpty');
const themeToggle = document.getElementById('dashboardThemeToggle');
const groupToggle = document.getElementById('dashboardGroupToggle');
const groupHeadings = [...dashboardSidebar.querySelectorAll('.dashboard-group-heading')];
const desktopViewport = window.matchMedia('(min-width: 900px)');
const dashboardRoot = document.documentElement;

function saveDashboardPreference(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (error) {}
}

function updateThemeButton() {
    const dark = dashboardRoot.dataset.pklTheme !== 'light';
    themeToggle.setAttribute('aria-pressed', String(dark));
    themeToggle.title = dark ? 'Ganti ke tema terang' : 'Ganti ke tema gelap';
    themeToggle.firstElementChild.classList.toggle('fa-sun', dark);
    themeToggle.firstElementChild.classList.toggle('fa-moon', !dark);
}

themeToggle.addEventListener('click', () => {
    const theme = dashboardRoot.dataset.pklTheme === 'light' ? 'dark' : 'light';
    dashboardRoot.dataset.pklTheme = theme;
    saveDashboardPreference('pkl-dashboard-theme', theme);
    updateThemeButton();
});

function updateSidebarToggle() {
    const expanded = desktopViewport.matches
        ? !dashboardRoot.classList.contains('pkl-compact-sidebar')
        : dashboardLayout.classList.contains('sidebar-open');
    sidebarToggle.setAttribute('aria-expanded', String(expanded));
    const compact = dashboardRoot.classList.contains('pkl-compact-sidebar');
    sidebarCollapse.querySelector('span').textContent = compact ? 'Lebarkan Sidebar' : 'Ciutkan Sidebar';
    sidebarCollapse.title = sidebarCollapse.querySelector('span').textContent;
    sidebarCollapse.firstElementChild.classList.toggle('fa-angles-left', !compact);
    sidebarCollapse.firstElementChild.classList.toggle('fa-angles-right', compact);
}

function toggleCompactSidebar() {
    const compact = dashboardRoot.classList.toggle('pkl-compact-sidebar');
    saveDashboardPreference('pkl-dashboard-sidebar', compact ? 'compact' : 'expanded');
    if (compact && sidebarSearch.value) {
        sidebarSearch.value = '';
        filterSidebarMenus();
    }
    updateSidebarToggle();
}

function setMobileSidebar(open) {
    dashboardLayout.classList.toggle('sidebar-open', open);
    sidebarBackdrop.hidden = !open;
    for (const element of document.querySelectorAll('.pkl-dashboard > nav, .pkl-dashboard > footer, .dashboard-layout > main')) {
        element.inert = open;
    }
    if (open) {
        dashboardSidebar.setAttribute('role', 'dialog');
        dashboardSidebar.setAttribute('aria-modal', 'true');
    } else {
        dashboardSidebar.removeAttribute('role');
        dashboardSidebar.removeAttribute('aria-modal');
    }
    updateSidebarToggle();
}

function closeMobileSidebar() {
    setMobileSidebar(false);
    sidebarToggle.focus();
}

sidebarToggle.addEventListener('click', () => {
    if (desktopViewport.matches) {
        toggleCompactSidebar();
    } else if (dashboardLayout.classList.contains('sidebar-open')) {
        closeMobileSidebar();
    } else {
        setMobileSidebar(true);
        sidebarClose.focus();
    }
});

sidebarClose.addEventListener('click', closeMobileSidebar);
sidebarCollapse.addEventListener('click', toggleCompactSidebar);
sidebarBackdrop.addEventListener('click', closeMobileSidebar);
desktopViewport.addEventListener('change', () => {
    const focusInSidebar = dashboardSidebar.contains(document.activeElement);
    setMobileSidebar(false);
    if (focusInSidebar && !document.activeElement.getClientRects().length) sidebarToggle.focus();
});

dashboardSidebar.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    const target = link && document.getElementById(link.hash.slice(1));
    if (!target || desktopViewport.matches) return;

    event.preventDefault();
    setMobileSidebar(false);
    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'start' });
});

document.addEventListener('keydown', event => {
    if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey
        && !event.target.closest?.('input, textarea, select, [contenteditable]')
        && sidebarSearch.getClientRects().length) {
        event.preventDefault();
        sidebarSearch.focus();
    }
    if (!dashboardLayout.classList.contains('sidebar-open')) return;
    if (event.key === 'Escape') {
        closeMobileSidebar();
        return;
    }
    if (event.key === 'Tab') {
        const controls = [...dashboardSidebar.querySelectorAll('button, input, a:not([hidden])')]
            .filter(control => control.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && (document.activeElement === first || !dashboardSidebar.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dashboardSidebar.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    }
});

function filterSidebarMenus() {
    const query = sidebarSearch.value.trim().toLocaleLowerCase('id');
    let hasMatches = false;
    dashboardSidebar.classList.toggle('sidebar-searching', Boolean(query));
    groupToggle.disabled = Boolean(query);

    for (const group of dashboardSidebar.querySelectorAll('.dashboard-sidebar-group')) {
        let groupMatches = false;
        for (const link of group.querySelectorAll('a')) {
            link.hidden = !link.textContent.toLocaleLowerCase('id').includes(query);
            groupMatches ||= !link.hidden;
        }
        group.hidden = !groupMatches;
        hasMatches ||= groupMatches;
    }

    menuEmpty.hidden = hasMatches;
}

function updateGroupToggle() {
    const anyExpanded = groupHeadings.some(button => button.getAttribute('aria-expanded') === 'true');
    groupToggle.querySelector('span').textContent = anyExpanded ? 'Ciutkan Semua' : 'Buka Semua';
}

for (const button of groupHeadings) {
    button.addEventListener('click', () => {
        if (dashboardSidebar.classList.contains('sidebar-searching')) return;
        button.setAttribute('aria-expanded', String(button.getAttribute('aria-expanded') !== 'true'));
        updateGroupToggle();
    });
}

groupToggle.addEventListener('click', () => {
    const anyExpanded = groupHeadings.some(button => button.getAttribute('aria-expanded') === 'true');
    for (const button of groupHeadings) button.setAttribute('aria-expanded', String(!anyExpanded));
    updateGroupToggle();
});

sidebarSearch.addEventListener('input', filterSidebarMenus);
updateThemeButton();
updateSidebarToggle();
