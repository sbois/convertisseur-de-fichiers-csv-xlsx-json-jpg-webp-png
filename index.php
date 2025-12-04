<?php
// Configuration
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $conversionType = $_POST['conversion_type'] ?? '';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmpName = $file['tmp_name'];
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        
        try {
            switch ($conversionType) {
                case 'jpg_to_webp':
                    $img = imagecreatefromjpeg($tmpName);
                    $outputName = $originalName . '.webp';
                    header('Content-Type: image/webp');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    imagewebp($img, null, 80);
                    imagedestroy($img);
                    exit;
                    
                case 'webp_to_jpg':
                    $img = imagecreatefromwebp($tmpName);
                    $outputName = $originalName . '.jpg';
                    header('Content-Type: image/jpeg');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    imagejpeg($img, null, 90);
                    imagedestroy($img);
                    exit;
                    
                case 'png_to_jpg':
                    $img = imagecreatefrompng($tmpName);
                    $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                    imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                    $outputName = $originalName . '.jpg';
                    header('Content-Type: image/jpeg');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    imagejpeg($bg, null, 90);
                    imagedestroy($img);
                    imagedestroy($bg);
                    exit;
                    
                case 'jpg_to_png':
                    $img = imagecreatefromjpeg($tmpName);
                    $outputName = $originalName . '.png';
                    header('Content-Type: image/png');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    imagepng($img);
                    imagedestroy($img);
                    exit;
                    
                case 'csv_to_json':
                    $csvData = array_map(function($line) {
                        return str_getcsv($line, ',', '"');
                    }, file($tmpName));
                    $headers = array_shift($csvData);
                    $jsonData = array_map(function($row) use ($headers) {
                        return array_combine($headers, $row);
                    }, $csvData);
                    $outputName = $originalName . '.json';
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit;
                    
                case 'json_to_csv':
                    $jsonContent = file_get_contents($tmpName);
                    $data = json_decode($jsonContent, true);
                    if (!is_array($data) || empty($data)) {
                        throw new Exception('Format JSON invalide');
                    }
                    $outputName = $originalName . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    $output = fopen('php://output', 'w');
                    fputcsv($output, array_keys($data[0]));
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                    fclose($output);
                    exit;
                    
                case 'csv_to_xlsx':
                    // Fonction pour nettoyer les caractères invalides en XML
                    function cleanXmlString($string) {
                        // Enlever les caractères de contrôle invalides en XML (sauf tab, newline, carriage return)
                        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
                        return $string;
                    }
                    
                    // Fonction pour obtenir la lettre de colonne Excel (A, B, C, ..., Z, AA, AB, ...)
                    function getColumnLetter($colIndex) {
                        $letter = '';
                        while ($colIndex >= 0) {
                            $letter = chr(65 + ($colIndex % 26)) . $letter;
                            $colIndex = intval($colIndex / 26) - 1;
                        }
                        return $letter;
                    }
                    
                    $csvData = [];
                    if (($handle = fopen($tmpName, 'r')) !== false) {
                        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                            $csvData[] = $row;
                        }
                        fclose($handle);
                    }
                    
                    $outputName = $originalName . '.xlsx';
                    $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx');
                    
                    $zip = new ZipArchive();
                    $zip->open($tempXlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    
                    // Construire les données du worksheet
                    $sheetData = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $sheetData .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
                    $sheetData .= '<sheetData>';
                    
                    foreach ($csvData as $rowIndex => $row) {
                        $sheetData .= '<row r="' . ($rowIndex + 1) . '">';
                        foreach ($row as $colIndex => $cell) {
                            $colLetter = getColumnLetter($colIndex);
                            // Nettoyer et encoder la cellule
                            $cleanCell = cleanXmlString($cell);
                            $cleanCell = mb_convert_encoding($cleanCell, 'UTF-8', mb_detect_encoding($cleanCell, 'UTF-8, ISO-8859-1, Windows-1252', true));
                            $cellValue = htmlspecialchars($cleanCell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                            
                            $sheetData .= '<c r="' . $colLetter . ($rowIndex + 1) . '" t="inlineStr">';
                            $sheetData .= '<is><t>' . $cellValue . '</t></is>';
                            $sheetData .= '</c>';
                        }
                        $sheetData .= '</row>';
                    }
                    
                    $sheetData .= '</sheetData></worksheet>';
                    
                    // Ajouter tous les fichiers nécessaires au zip
                    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetData);
                    
                    $zip->addFromString('[Content_Types].xml', 
                        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
                        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                        '<Default Extension="xml" ContentType="application/xml"/>' .
                        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
                        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
                        '</Types>');
                    
                    $zip->addFromString('xl/workbook.xml',
                        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
                        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                        '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>' .
                        '</workbook>');
                    
                    $zip->addFromString('_rels/.rels',
                        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
                        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
                        '</Relationships>');
                    
                    $zip->addFromString('xl/_rels/workbook.xml.rels',
                        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
                        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
                        '</Relationships>');
                    
                    $zip->close();
                    
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment; filename="' . $outputName . '"');
                    readfile($tempXlsx);
                    unlink($tempXlsx);
                    exit;
                    
                default:
                    throw new Exception('Type de conversion non supporté');
            }
        } catch (Exception $e) {
            $message = 'Erreur: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Erreur lors du téléchargement du fichier';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convertisseur Industriel - Les Temps Modernes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #2c2416 0%, #4a3f2e 100%);
            color: #d4c5a9;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(139, 90, 43, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(205, 133, 63, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .gear {
            position: fixed;
            opacity: 0.08;
            animation: rotate 20s linear infinite;
            pointer-events: none;
        }
        
        .gear1 {
            top: 10%;
            left: 5%;
            font-size: 120px;
            animation-duration: 25s;
        }
        
        .gear2 {
            top: 60%;
            right: 8%;
            font-size: 150px;
            animation-direction: reverse;
        }
        
        .gear3 {
            bottom: 15%;
            left: 15%;
            font-size: 100px;
            animation-duration: 30s;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .header {
            text-align: center;
            padding: 40px 20px;
            border: 3px solid #8b5a2b;
            background: rgba(34, 28, 20, 0.95);
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 
                0 0 30px rgba(139, 90, 43, 0.4),
                inset 0 0 20px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .header::before,
        .header::after {
            content: '⚙';
            position: absolute;
            font-size: 40px;
            color: #8b5a2b;
            opacity: 0.6;
        }
        
        .header::before {
            top: 10px;
            left: 20px;
            animation: rotate 10s linear infinite;
        }
        
        .header::after {
            top: 10px;
            right: 20px;
            animation: rotate 10s linear infinite reverse;
        }
        
        h1 {
            font-size: 2.5em;
            color: #cd853f;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .subtitle {
            font-size: 1.1em;
            color: #a0826d;
            font-style: italic;
        }
        
        .converter-box {
            background: rgba(42, 35, 25, 0.95);
            border: 3px solid #8b5a2b;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.6),
                inset 0 0 20px rgba(139, 90, 43, 0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #cd853f;
            font-weight: bold;
            font-size: 1.1em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        select, input[type="file"] {
            width: 100%;
            padding: 12px;
            background: rgba(20, 16, 12, 0.9);
            border: 2px solid #8b5a2b;
            border-radius: 5px;
            color: #d4c5a9;
            font-family: 'Courier New', monospace;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        select:focus, input[type="file"]:focus {
            outline: none;
            border-color: #cd853f;
            box-shadow: 0 0 10px rgba(205, 133, 63, 0.5);
        }
        
        select {
            cursor: pointer;
        }
        
        option {
            background: #1a1410;
            color: #d4c5a9;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #8b5a2b 0%, #a0826d 100%);
            border: 3px solid #cd853f;
            border-radius: 5px;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 
                0 4px 15px rgba(139, 90, 43, 0.4),
                inset 0 -2px 5px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '⚡';
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5em;
            transition: left 0.3s ease;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #a0826d 0%, #cd853f 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 6px 20px rgba(205, 133, 63, 0.6),
                inset 0 -2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .btn:hover::before {
            left: 20px;
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: 
                0 2px 10px rgba(139, 90, 43, 0.4),
                inset 0 2px 5px rgba(0, 0, 0, 0.5);
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            border: 2px solid;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.error {
            background: rgba(139, 0, 0, 0.2);
            border-color: #8b0000;
            color: #ff6b6b;
        }
        
        .message.success {
            background: rgba(0, 100, 0, 0.2);
            border-color: #228b22;
            color: #90ee90;
        }
        
        .conversion-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
            padding: 20px;
            background: rgba(20, 16, 12, 0.5);
            border-radius: 8px;
            border: 2px dashed #8b5a2b;
        }
        
        .conversion-item {
            text-align: center;
            padding: 15px;
            background: rgba(42, 35, 25, 0.8);
            border: 1px solid #8b5a2b;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .conversion-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(205, 133, 63, 0.3);
            border-color: #cd853f;
        }
        
        .conversion-item strong {
            color: #cd853f;
            font-size: 1.1em;
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 1.8em;
            }
            
            .converter-box {
                padding: 20px;
            }
            
            .gear {
                font-size: 60px !important;
            }
            
            .conversion-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .steam {
            position: fixed;
            bottom: -50px;
            width: 2px;
            height: 100px;
            background: linear-gradient(to top, transparent, rgba(212, 197, 169, 0.3));
            animation: steam 8s ease-in infinite;
            pointer-events: none;
        }
        
        @keyframes steam {
            0% {
                transform: translateY(0) translateX(0) scaleX(1);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.3;
            }
            100% {
                transform: translateY(-100vh) translateX(50px) scaleX(3);
                opacity: 0;
            }
        }
        
        .steam:nth-child(1) { left: 10%; animation-delay: 0s; }
        .steam:nth-child(2) { left: 30%; animation-delay: 2s; }
        .steam:nth-child(3) { left: 50%; animation-delay: 4s; }
        .steam:nth-child(4) { left: 70%; animation-delay: 6s; }
        .steam:nth-child(5) { left: 90%; animation-delay: 1s; }
    </style>
</head>
<body>
    <div class="gear gear1">⚙</div>
    <div class="gear gear2">⚙</div>
    <div class="gear gear3">⚙</div>
    
    <div class="steam"></div>
    <div class="steam"></div>
    <div class="steam"></div>
    <div class="steam"></div>
    <div class="steam"></div>
    
    <div class="container">
        <div class="header">
            <h1>⚙ CONVERTISSEUR INDUSTRIEL ⚙</h1>
            <p class="subtitle">Manufacture Automatique de Transformation de Fichiers</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="converter-box">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="conversion_type">⚡ Type de Conversion</label>
                    <select name="conversion_type" id="conversion_type" required>
                        <option value="">-- Sélectionnez une conversion --</option>
                        <optgroup label="🖼️ Images">
                            <option value="jpg_to_webp">JPG → WebP</option>
                            <option value="webp_to_jpg">WebP → JPG</option>
                            <option value="png_to_jpg">PNG → JPG</option>
                            <option value="jpg_to_png">JPG → PNG</option>
                        </optgroup>
                        <optgroup label="📊 Données">
                            <option value="csv_to_json">CSV → JSON</option>
                            <option value="json_to_csv">JSON → CSV</option>
                            <option value="csv_to_xlsx">CSV → XLSX (Windows)</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="file">📁 Fichier à Convertir</label>
                    <input type="file" name="file" id="file" required>
                </div>
                
                <button type="submit" class="btn">⚙ Lancer la Machine ⚙</button>
            </form>
            
            <div class="conversion-grid">
                <div class="conversion-item">
                    <strong>JPG ⇄ WebP</strong><br>
                    <small>Compression moderne</small>
                </div>
                <div class="conversion-item">
                    <strong>PNG ⇄ JPG</strong><br>
                    <small>Format universel</small>
                </div>
                <div class="conversion-item">
                    <strong>CSV ⇄ JSON</strong><br>
                    <small>Données structurées</small>
                </div>
                <div class="conversion-item">
                    <strong>CSV → XLSX</strong><br>
                    <small>Excel compatible</small>
                </div>
            </div>
        </div>
    </div>
    <div style="text-align:center; margin-top:15px; opacity:0.7;">
    <!-- Icône GPLv3 -->
    <a href="https://www.gnu.org/licenses/gpl-3.0.fr.html" target="_blank" style="margin-right:12px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/GPLv3_Logo.svg"
             alt="GNU GPLv3"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>

    <!-- Icône GitHub fond transparent -->
    <a href="https://github.com/sbois" target="_blank">
        <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/github.svg"
             alt="GitHub"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>
</div>

</body>
</html>