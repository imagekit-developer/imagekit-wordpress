/**
 * System Report
 *
 * Handles copy-to-clipboard and download functionality for the system report page.
 */
class SystemReport {
    constructor() {
        this.copyButton = document.getElementById('ik-copy-report');
        this.downloadButton = document.getElementById('ik-download-report');
        this.notice = document.getElementById('ik-copy-notice');

        if (this.copyButton) {
            this.copyButton.addEventListener('click', () => this.copyToClipboard());
        }

        if (this.downloadButton) {
            this.downloadButton.addEventListener('click', () => this.downloadReport());
        }
    }

    getReportText() {
        const el = this.copyButton || this.downloadButton;
        return el ? el.getAttribute('data-report') : '';
    }

    async copyToClipboard() {
        const text = this.getReportText();
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            this.showNotice();
        } catch (err) {
            // Fallback for older browsers.
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showNotice();
        }
    }

    showNotice() {
        if (!this.notice) {
            return;
        }

        this.notice.hidden = false;
        clearTimeout(this.noticeTimeout);
        this.noticeTimeout = setTimeout(() => {
            this.notice.hidden = true;
        }, 2000);
    }

    downloadReport() {
        const text = this.getReportText();
        if (!text) {
            return;
        }

        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'imagekit-system-report.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
}

const initSystemReport = () => {
    if (document.querySelector('.ik-system-report')) {
        new SystemReport();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSystemReport);
} else {
    initSystemReport();
}

export default SystemReport;
