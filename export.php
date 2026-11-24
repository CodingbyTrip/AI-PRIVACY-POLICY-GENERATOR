<?php
require_once 'config.php';

$format      = 'txt';
$websiteUrl  = 'https://example.com';
$companyName = 'Website';
$markdown    = '';

// Support direct GET download from index.php (?id=12&format=docx)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM policies WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $policy = $stmt->fetch();

    if (!$policy) {
        die('Policy not found.');
    }

    $format      = $_GET['format'] ?? 'docx';
    $websiteUrl  = $policy['website_url'];
    $companyName = $policy['company_name'] ?: 'Website';
    $markdown    = $policy['policy_content'];
}
// Support POST download from view_policy.php
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format      = $_POST['format'] ?? 'txt';
    $websiteUrl  = trim($_POST['website_url'] ?? 'https://example.com');
    $companyName = trim($_POST['company_name'] ?? 'Website');
    $markdown    = $_POST['policy_content'] ?? '';
} else {
    header('Location: index.php');
    exit;
}

$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(substr($companyName, 0, 40) ?: 'privacy_policy'));

// --- TXT EXPORT ---
if ($format === 'txt') {
    $plainText = preg_replace('/^#+\s*/m', '', $markdown);
    $plainText = str_replace(['**', '__', '`'], '', $plainText);
    $plainText .= "\n\n----------------------------------------\n";
    $plainText .= "Verified Website URL: " . $websiteUrl . "\n";
    $plainText .= "Generated on: " . date('Y-m-d H:i:s') . "\n";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '_privacy_policy.txt"');
    echo $plainText;
    exit;
}

// --- NATIVE .DOCX EXPORT ---
if ($format === 'doc' || $format === 'docx') {
    if (!class_exists('ZipArchive')) {
        die('Please enable extension=zip in your php.ini and restart Apache.');
    }

    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&format=png&data=" . urlencode($websiteUrl);
    $ch = curl_init($qrApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    $qrBinary = curl_exec($ch);
    curl_close($ch);
    $hasQr = !empty($qrBinary);

    $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
    $bodyXml = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $bodyXml .= '<w:p/>';
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $matches)) {
            $level = strlen($matches[1]);
            $text  = htmlspecialchars(str_replace('**', '', $matches[2]), ENT_XML1, 'UTF-8');
            $size  = ($level === 1) ? '32' : (($level === 2) ? '26' : '22');
            $color = ($level === 1) ? '0F172A' : '1E3A8A';

            $bodyXml .= "
            <w:p>
                <w:pPr><w:spacing w:before='200' w:after='100'/></w:pPr>
                <w:r>
                    <w:rPr><w:b/><w:color w:val='$color'/><w:sz w:val='$size'/></w:rPr>
                    <w:t xml:space='preserve'>$text</w:t>
                </w:r>
            </w:p>";
            continue;
        }

        $isBullet = false;
        if (preg_match('/^[\*\-]\s+(.*)$/', $trimmed, $matches)) {
            $isBullet = true;
            $trimmed = '• ' . $matches[1];
        }

        $parts = preg_split('/(\*\*.*?\*\*)/', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $runsXml = '';
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.*)\*\*$/', $part, $bMatch)) {
                $clean = htmlspecialchars($bMatch[1], ENT_XML1, 'UTF-8');
                $runsXml .= "<w:r><w:rPr><w:b/><w:sz w:val='22'/></w:rPr><w:t xml:space='preserve'>$clean</w:t></w:r>";
            } else {
                $clean = htmlspecialchars($part, ENT_XML1, 'UTF-8');
                $runsXml .= "<w:r><w:rPr><w:sz w:val='22'/></w:rPr><w:t xml:space='preserve'>$clean</w:t></w:r>";
            }
        }

        $indent = $isBullet ? "<w:ind w:left='360'/>" : "";
        $bodyXml .= "<w:p><w:pPr>$indent<w:spacing w:after='100'/></w:pPr>$runsXml</w:p>";
    }

    $safeUrlXml = htmlspecialchars($websiteUrl, ENT_XML1, 'UTF-8');
    $bodyXml .= "
    <w:p><w:pPr><w:spacing w:before='300' w:after='80'/></w:pPr>
        <w:r><w:rPr><w:b/><w:color w:val='1E3A8A'/><w:sz w:val='24'/></w:rPr>
        <w:t>Official Website Verification</w:t></w:r>
    </w:p>
    <w:p>
        <w:r><w:rPr><w:sz w:val='20'/></w:rPr>
        <w:t xml:space='preserve'>Scan the QR code below or visit: $safeUrlXml</w:t></w:r>
    </w:p>";

    if ($hasQr) {
        $bodyXml .= "
        <w:p>
            <w:r>
                <w:drawing>
                    <wp:inline distT='0' distB='0' distL='0' distR='0'>
                        <wp:extent cx='1428750' cy='1428750'/>
                        <wp:docPr id='1' name='Website QR Code'/>
                        <a:graphic xmlns:a='http://schemas.openxmlformats.org/drawingml/2006/main'>
                            <a:graphicData uri='http://schemas.openxmlformats.org/drawingml/2006/picture'>
                                <pic:pic xmlns:pic='http://schemas.openxmlformats.org/drawingml/2006/picture'>
                                    <pic:nvPicPr>
                                        <pic:cNvPr id='0' name='qr.png'/>
                                        <pic:cNvPicPr/>
                                    </pic:nvPicPr>
                                    <pic:blipFill>
                                        <a:blip r:embed='rId1'/>
                                        <a:stretch><a:fillRect/></a:stretch>
                                    </pic:blipFill>
                                    <pic:spPr>
                                        <a:xfrm><a:off x='0' y='0'/><a:ext cx='1428750' cy='1428750'/></a:xfrm>
                                        <a:prstGeom prst='rect'><a:avLst/></a:prstGeom>
                                    </pic:spPr>
                                </pic:pic>
                            </a:graphicData>
                        </a:graphic>
                    </wp:inline>
                </w:drawing>
            </w:r>
        </w:p>";
    }

    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
                xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">
        <w:body>' . $bodyXml . '</w:body>
    </w:document>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
        <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
        <Default Extension="xml" ContentType="application/xml"/>
        <Default Extension="png" ContentType="image/png"/>
        <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    </Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/qr.png"/>
    </Relationships>';

    $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
    </Relationships>';

    $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::OVERWRITE) === true) {
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
        if ($hasQr) {
            $zip->addFromString('word/media/qr.png', $qrBinary);
        }
        $zip->close();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '_privacy_policy.docx"');
    header('Content-Length: ' . filesize($tempFile));
    readfile($tempFile);
    unlink($tempFile);
    exit;
}
?>