// let currentPolicy = {
//     url: '',
//     company: '',
//     markdown: ''
// };

// function renderPolicyDisplay(url, company, markdown, signals = null) {
//     currentPolicy = { url, company, markdown };

//     document.getElementById('policyOutput').innerHTML = marked.parse(markdown);

//     // Render QR Code inside the Privacy Policy
//     const qrContainer = document.getElementById('policyQrCode');
//     qrContainer.innerHTML = '';
//     new QRCode(qrContainer, {
//         text: url,
//         width: 110,
//         height: 110,
//         colorDark: '#0f172a',
//         colorLight: '#ffffff',
//         correctLevel: QRCode.CorrectLevel.M
//     });

//     const qrLink = document.getElementById('qrSiteLink');
//     qrLink.href = url;
//     qrLink.textContent = url;

//     const badgesEl = document.getElementById('signalBadges');
//     if (signals) {
//         badgesEl.innerHTML = `
//             <span class="badge">Form Inputs Detected: ${signals.inputs_found}</span>
//             <span class="badge">External Scripts Detected: ${signals.scripts_found}</span>
//         `;
//         badgesEl.classList.remove('hidden');
//     } else {
//         badgesEl.classList.add('hidden');
//     }

//     const resultSection = document.getElementById('resultSection');
//     resultSection.classList.remove('hidden');
//     resultSection.scrollIntoView({ behavior: 'smooth' });
// }

// document.getElementById('policyForm').addEventListener('submit', async (e) => {
//     e.preventDefault();

//     const url = document.getElementById('url').value.trim();
//     const company_name = document.getElementById('company_name').value.trim();
//     const contact_email = document.getElementById('contact_email').value.trim();

//     const btn = document.getElementById('generateBtn');
//     const status = document.getElementById('statusMessage');

//     btn.disabled = true;
//     status.className = 'status loading';
//     status.textContent = 'Scanning website structure and generating tailored privacy policy...';
//     status.classList.remove('hidden');

//     try {
//         const res = await fetch('generate.php', {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ url, company_name, contact_email })
//         });

//         const rawText = await res.text();
//         let data;
//         try {
//             data = JSON.parse(rawText);
//         } catch (parseErr) {
//             throw new Error(rawText.replace(/<[^>]*>/g, '').trim() || 'Invalid server response');
//         }

//         if (data.status === 'success') {
//             status.classList.add('hidden');
//             renderPolicyDisplay(url, company_name || url, data.policy, data.signals);
//         } else {
//             status.className = 'status error';
//             status.textContent = data.message || 'An error occurred while generating the policy.';
//         }
//     } catch (err) {
//         status.className = 'status error';
//         status.textContent = 'Error: ' + err.message;
//     } finally {
//         btn.disabled = false;
//     }
// });

// // View a previously saved policy from MySQL
// async function viewSavedPolicy(id) {
//     const status = document.getElementById('statusMessage');
//     try {
//         const res = await fetch(`export.php?view_id=${id}`);
//         const result = await res.json();
//         if (result.status === 'success') {
//             status.classList.add('hidden');
//             renderPolicyDisplay(
//                 result.data.website_url,
//                 result.data.company_name,
//                 result.data.policy_content
//             );
//         } else {
//             alert(result.message);
//         }
//     } catch (err) {
//         alert('Could not load saved policy.');
//     }
// }

// // Copy Markdown
// document.getElementById('copyBtn').addEventListener('click', () => {
//     navigator.clipboard.writeText(currentPolicy.markdown);
//     const btn = document.getElementById('copyBtn');
//     btn.textContent = 'Copied!';
//     setTimeout(() => (btn.textContent = 'Copy'), 2000);
// });

// // Download PDF (uses clean @media print stylesheet including QR code)
// document.getElementById('pdfBtn').addEventListener('click', () => {
//     window.print();
// });

// // Submit hidden form to export.php for .DOC and .TXT downloads
// function triggerServerDownload(format) {
//     document.getElementById('exportFormat').value = format;
//     document.getElementById('exportUrl').value = currentPolicy.url;
//     document.getElementById('exportCompany').value = currentPolicy.company;
//     document.getElementById('exportContent').value = currentPolicy.markdown;
//     document.getElementById('exportForm').submit();
// }

// document.getElementById('docBtn').addEventListener('click', () => triggerServerDownload('doc'));
// document.getElementById('txtBtn').addEventListener('click', () => triggerServerDownload('txt'));
// // Direct PDF File Download (only exports #printablePolicy + QR Code)
// document.getElementById('pdfBtn').addEventListener('click', () => {
//     const pdfBtn = document.getElementById('pdfBtn');
//     const element = document.getElementById('printablePolicy');

//     // Temporarily expand scroll container so full multi-page policy renders in PDF
//     const prevMaxHeight = element.style.maxHeight;
//     const prevOverflow = element.style.overflow;
//     element.style.maxHeight = 'none';
//     element.style.overflow = 'visible';

//     pdfBtn.disabled = true;
//     pdfBtn.textContent = 'Generating PDF...';

//     const safeName = (currentPolicy.company || 'privacy_policy')
//         .replace(/[^a-zA-Z0-9_-]/g, '_')
//         .toLowerCase();

//     const opt = {
//         margin:       [12, 12, 12, 12],
//         filename:     `${safeName}_privacy_policy.pdf`,
//         image:        { type: 'jpeg', quality: 0.98 },
//         html2canvas:  { scale: 2, useCORS: true, scrollY: 0 },
//         jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
//         pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
//     };

//     html2pdf().set(opt).from(element).save().then(() => {
//         element.style.maxHeight = prevMaxHeight;
//         element.style.overflow = prevOverflow;
//         pdfBtn.disabled = false;
//         pdfBtn.textContent = 'Download PDF';
//     }).catch(() => {
//         element.style.maxHeight = prevMaxHeight;
//         element.style.overflow = prevOverflow;
//         pdfBtn.disabled = false;
//         pdfBtn.textContent = 'Download PDF';
//     });
// });
document.getElementById('policyForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const url = document.getElementById('url').value.trim();
    const company_name = document.getElementById('company_name').value.trim();
    const contact_email = document.getElementById('contact_email').value.trim();

    const btn = document.getElementById('generateBtn');
    const status = document.getElementById('statusMessage');

    btn.disabled = true;
    status.className = 'status loading';
    status.textContent = 'Scanning website structure and generating tailored privacy policy...';
    status.classList.remove('hidden');

    try {
        const res = await fetch('generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, company_name, contact_email })
        });

        const rawText = await res.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (parseErr) {
            throw new Error(rawText.replace(/<[^>]*>/g, '').trim() || 'Invalid server response');
        }

        if (data.status === 'success' && data.policy_id) {
            status.textContent = 'Policy generated! Opening policy page...';
            window.location.href = `view_policy.php?id=${data.policy_id}`;
        } else {
            status.className = 'status error';
            status.textContent = data.message || 'An error occurred while generating the policy.';
            btn.disabled = false;
        }
    } catch (err) {
        status.className = 'status error';
        status.textContent = 'Error: ' + err.message;
        btn.disabled = false;
    }
});