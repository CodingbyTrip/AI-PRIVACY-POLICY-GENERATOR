<?php
require_once 'config.php';

// Handle Policy Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteStmt = $pdo->prepare("DELETE FROM policies WHERE id = ?");
    $deleteStmt->execute([(int)$_POST['delete_id']]);
    header("Location: index.php?deleted=1");
    exit;
}

$recentPolicies = $pdo->query("SELECT id, website_url, company_name, created_at FROM policies ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Privacy Policy Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3">
</head>
<body>
    <div class="app-wrapper">
        <header class="hero-header">
            <div class="pill-badge">AI Compliance & Legal Scanner</div>
            <h1>AI Privacy Policy Generator</h1>
            <p>Scan any live website's forms, cookies, and third-party scripts to generate a tailored, ready-to-publish privacy policy with QR verification.</p>
        </header>
<?php if (isset($_GET['deleted'])): ?>
            <div id="toastBanner" class="toast-banner">Policy deleted successfully.</div>
            <script>
                // Remove ?deleted=1 from the URL so refreshing doesn't show it again
                window.history.replaceState({}, document.title, window.location.pathname);
                setTimeout(() => {
                    const toast = document.getElementById('toastBanner');
                    if (toast) {
                        toast.style.transition = 'opacity 0.4s ease';
                        toast.style.opacity = '0';
                        setTimeout(() => toast.remove(), 400);
                    }
                }, 3000);
            </script>
        <?php endif; ?>

        <!-- Two-Column Side-by-Side Split Layout -->
        <main class="split-grid">
            <!-- LEFT SECTION: URL Scanner & Generator -->
            <section class="card scanner-card">
                <div class="card-header">
                    <div class="icon-box blue">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                    </div>
                    <div>
                        <h2>Generate New Policy</h2>
                        <span class="subtitle">Analyze live site metadata & tracking scripts</span>
                    </div>
                </div>

                <form id="policyForm">
                    <div class="form-group">
                        <label for="url">Target Website URL <span class="req">*</span></label>
                        <input type="url" id="url" name="url" placeholder="https://example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="company_name">Company / Brand Name <span class="opt">(Optional)</span></label>
                        <input type="text" id="company_name" name="company_name" placeholder="Auto-detected from website title if blank">
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Privacy Contact Email <span class="opt">(Optional)</span></label>
                        <input type="email" id="contact_email" name="contact_email" placeholder="privacy@example.com">
                    </div>

                    <button type="submit" id="generateBtn" class="btn-primary-full">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Scan & Generate Privacy Policy
                    </button>
                </form>

                <div id="statusMessage" class="status hidden"></div>

                <div class="feature-hints">
                    <div class="hint-item"><strong>Auto-Scraping:</strong> Detects input fields, forms & external scripts</div>
                    <div class="hint-item"><strong>Multi-Format:</strong> Export as PDF, DOCX, or TXT with QR code</div>
                </div>
            </section>

            <!-- RIGHT SECTION: Saved & Generated Policies -->
            <section class="card history-card">
                <div class="card-header">
                    <div class="icon-box indigo">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h2>Saved Policies (<?= count($recentPolicies) ?>)</h2>
                        <span class="subtitle">View, download, or manage generated documents</span>
                    </div>
                </div>

                <?php if (empty($recentPolicies)): ?>
                    <div class="empty-state">
                        <p>No policies generated yet.</p>
                        <span>Enter a website URL on the left to create your first privacy policy.</span>
                    </div>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach ($recentPolicies as $item): ?>
                            <li class="history-item">
                                <div class="history-info">
                                    <strong title="<?= htmlspecialchars($item['company_name'] ?: 'Untitled Site') ?>">
                                        <?= htmlspecialchars($item['company_name'] ?: 'Untitled Site') ?>
                                    </strong>
                                    <a href="<?= htmlspecialchars($item['website_url']) ?>" target="_blank" class="site-url">
                                        <?= htmlspecialchars($item['website_url']) ?>
                                    </a>
                                    <small class="timestamp">
                                        Generated <?= date('M d, Y • h:i A', strtotime($item['created_at'])) ?>
                                    </small>
                                </div>

                                <!-- Three Distinct Action Buttons: View, Download, Delete -->
                                <div class="item-actions">
                                    <a href="view_policy.php?id=<?= (int)$item['id'] ?>" class="action-btn btn-view" title="View Policy & PDF">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        View
                                    </a>

                                    <a href="export.php?id=<?= (int)$item['id'] ?>&format=docx" class="action-btn btn-download" title="Download Word (.DOCX)">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>

                                    <form method="POST" action="index.php" class="inline-form" onsubmit="return confirm('Delete this privacy policy permanently?');">
                                        <input type="hidden" name="delete_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="action-btn btn-delete" title="Delete Policy">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="assets/app.js?v=3"></script>
</body>
</html>