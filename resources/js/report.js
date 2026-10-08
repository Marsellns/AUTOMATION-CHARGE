const applyReportTheme = () => {
    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    const oldTheme = document.documentElement.getAttribute('data-bs-theme');
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('.simaster-report').forEach(report => report.setAttribute('data-bs-theme', theme));
    if (oldTheme !== theme) {
        window.dispatchEvent(new CustomEvent('simaster:theme-changed', { detail: { theme } }));
    }
};

applyReportTheme();
new MutationObserver(applyReportTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
