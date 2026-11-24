<?php
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM policies WHERE id = ?");
$stmt->execute([$id]);
$policy = $stmt->fetch();

if (!$policy) {
    header('Location: index.php');
    exit;
}

$features = json_decode($policy['detected_features'] ?? '{}', true);
$inputsCount = isset($features['inputs']) ? count($features['inputs']) : 0;
$scriptsCount = isset($features['scripts']) ? count($features['scripts']) : 0;
$displayCompany = $policy['company_name'] ?: $policy['website_url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - <?= htmlspecialchars($displayCompany) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- Top Navigation & Export Toolbar -->
        <div class="card no-print">
            <div class="result-header">
                <a href="index.php" class="btn-secondary btn-link">&larr; Back to Generator</a>
                <div class="actions">
                    <button type="button" id="copyBtn" class="btn-secondary">Copy</button>
                    <button type="button" id="pdfBtn" class="btn-export pdf">Download PDF</button>
                    <button type="button" id="docBtn" class="btn-export doc">Download DOCX</button>
                    <button type="button" id="txtBtn" class="btn-export txt">Download TXT</button>
                </div>
            </div>
            <div class="badges" style="margin-bottom: 0;">
                <span class="badge">Form Inputs Detected: <?= $inputsCount ?></span>
                <span class="badge">External Scripts Detected: <?= $scriptsCount ?></span>
                <span class="badge">Generated: <?= date('M d, Y H:i', strtotime($policy['created_at'])) ?></span>
            </div>
        </div>

        <!-- Dedicated Policy Document + QR Code -->
        <section class="card">
            <div id="printablePolicy" class="policy-document full-view">
                <div id="policyOutput" class="policy-content"></div>

                <div class="policy-qr-box">
                    <div id="policyQrCode"></div>
                    <div class="qr-details">
                        <h4>Official Website Verification</h4>
                        <p>Scan this QR code to verify and visit the official website covered by this Privacy Policy:</p>
                        <a href="<?= htmlspecialchars($policy['website_url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars($policy['website_url']) ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hidden Form for Server .DOCX and .TXT Downloads -->
        <form id="exportForm" action="export.php" method="POST" target="_blank" class="hidden">
            <input type="hidden" name="format" id="exportFormat">
            <input type="hidden" name="website_url" value="<?= htmlspecialchars($policy['website_url']) ?>">
            <input type="hidden" name="company_name" value="<?= htmlspecialchars($displayCompany) ?>">
            <textarea name="policy_content" id="exportContent"><?= htmlspecialchars($policy['policy_content']) ?></textarea>
        </form>
    </div>

    <script>
        const rawMarkdown = <?= json_encode($policy['policy_content']) ?>;
        const targetUrl   = <?= json_encode($policy['website_url']) ?>;
        const companyName = <?= json_encode($displayCompany) ?>;

        // Render Markdown Policy
        document.getElementById('policyOutput').innerHTML = marked.parse(rawMarkdown);

        // Render QR Code
        new QRCode(document.getElementById('policyQrCode'), {
            text: targetUrl,
            width: 110,
            height: 110,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });

        // Copy Markdown
        document.getElementById('copyBtn').addEventListener('click', () => {
            navigator.clipboard.writeText(rawMarkdown);
            const btn = document.getElementById('copyBtn');
            btn.textContent = 'Copied!';
            setTimeout(() => (btn.textContent = 'Copy'), 2000);
        });

        // Direct PDF File Download
        document.getElementById('pdfBtn').addEventListener('click', () => {
            const pdfBtn = document.getElementById('pdfBtn');
            const element = document.getElementById('printablePolicy');

            pdfBtn.disabled = true;
            pdfBtn.textContent = 'Generating PDF...';

            const safeName = (companyName || 'privacy_policy')
                .replace(/[^a-zA-Z0-9_-]/g, '_')
                .toLowerCase();

            const opt = {
                margin:      [12, 12, 12, 12],
                filename:    `${safeName}_privacy_policy.pdf`,
                image:       { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
                jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save().finally(() => {
                pdfBtn.disabled = false;
                pdfBtn.textContent = 'Download PDF';
            });
        });

        // Server DOCX & TXT Downloads
        function triggerDownload(format) {
            document.getElementById('exportFormat').value = format;
            document.getElementById('exportForm').submit();
        }

        document.getElementById('docBtn').addEventListener('click', () => triggerDownload('doc'));
        document.getElementById('txtBtn').addEventListener('click', () => triggerDownload('txt'));
    </script>
</body>
</html>