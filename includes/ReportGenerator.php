<?php
// =============================================
// ReportGenerator.php - MS Word Report Generator
// Automated Trash Bin System
// =============================================

class ReportGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Export bin list to MS Word format
     */
    public function exportBinList($format = 'word') {
        // Fetch all bins with fill levels
        $stmt = $this->pdo->query("
            SELECT 
                tb.*,
                COALESCE(vbs.fill_level, 0) as fill_level,
                COALESCE(vbs.temperature, 0) as temperature,
                COALESCE(vbs.humidity, 0) as humidity,
                COALESCE(vbs.last_updated, tb.created_at) as last_updated
            FROM trash_bins tb
            LEFT JOIN v_bin_status vbs ON tb.bin_id = vbs.bin_id
            WHERE tb.status = 'active' OR tb.status = 'maintenance' OR tb.status = 'inactive'
            ORDER BY tb.bin_id
        ");
        $bins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics
        $totalBins = count($bins);
        $activeBins = count(array_filter($bins, function($b) { return $b['status'] === 'active'; }));
        $maintenanceBins = count(array_filter($bins, function($b) { return $b['status'] === 'maintenance'; }));
        
        // Get bin types breakdown
        $typeCounts = [];
        foreach ($bins as $bin) {
            $type = $bin['bin_type'] ?? 'general';
            if (!isset($typeCounts[$type])) {
                $typeCounts[$type] = 0;
            }
            $typeCounts[$type]++;
        }
        
        // Get average fill level
        $avgFill = 0;
        if ($totalBins > 0) {
            $sumFill = array_sum(array_column($bins, 'fill_level'));
            $avgFill = $sumFill / $totalBins;
        }
        
        // Build the Word document
        $this->generateWordDocument($bins, [
            'totalBins' => $totalBins,
            'activeBins' => $activeBins,
            'maintenanceBins' => $maintenanceBins,
            'typeCounts' => $typeCounts,
            'avgFill' => $avgFill,
            'generatedAt' => date('F d, Y h:i:s A'),
            'generatedBy' => $_SESSION['full_name'] ?? 'System'
        ]);
    }
    
    /**
     * Generate a Word document (.doc) with the bin list
     */
    private function generateWordDocument($bins, $stats) {
        // Set the content type as Word document
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="SmartBin_Report_' . date('Y-m-d') . '.doc"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Build the HTML content for Word
        echo $this->buildWordHTML($bins, $stats);
    }
    
    /**
     * Build the HTML content for the Word document
     */
    private function buildWordHTML($bins, $stats) {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                       xmlns:w="urn:schemas-microsoft-com:office:word" 
                       xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="UTF-8">
            <title>SmartBin - Bin Locations Report</title>
            <!--[if gte mso 9]>
            <xml>
                <w:WordDocument>
                    <w:View>Print</w:View>
                    <w:Zoom>100</w:Zoom>
                </w:WordDocument>
            </xml>
            <![endif]-->
            <style>
                @page {
                    size: 8.5in 11in;
                    margin: 1in 0.75in 1in 0.75in;
                    mso-page-orientation: portrait;
                }
                
                body {
                    font-family: "Segoe UI", Arial, sans-serif;
                    font-size: 11pt;
                    color: #333333;
                    line-height: 1.6;
                }
                
                .report-header {
                    border-bottom: 3px solid #8BC34A;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                
                .report-header h1 {
                    font-size: 28pt;
                    color: #2E7D32;
                    margin: 0;
                    font-weight: 700;
                }
                
                .report-header .subtitle {
                    font-size: 14pt;
                    color: #558B2F;
                    margin-top: 5px;
                }
                
                .report-header .meta {
                    font-size: 10pt;
                    color: #757575;
                    margin-top: 8px;
                }
                
                .section-title {
                    font-size: 16pt;
                    font-weight: 700;
                    color: #2E7D32;
                    border-bottom: 2px solid #C5E1A5;
                    padding-bottom: 8px;
                    margin-top: 24px;
                    margin-bottom: 16px;
                }
                
                .stats-grid {
                    display: table;
                    width: 100%;
                    margin-bottom: 20px;
                    border-collapse: collapse;
                }
                
                .stats-grid .stat-item {
                    display: table-cell;
                    padding: 12px 16px;
                    background-color: #F5F5F5;
                    border: 1px solid #E0E0E0;
                    text-align: center;
                    font-weight: 600;
                }
                
                .stats-grid .stat-value {
                    font-size: 20pt;
                    color: #8BC34A;
                    display: block;
                    font-weight: 700;
                }
                
                .stats-grid .stat-label {
                    font-size: 10pt;
                    color: #757575;
                    font-weight: 400;
                }
                
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 12px;
                    font-size: 10pt;
                }
                
                .data-table th {
                    background-color: #8BC34A;
                    color: #FFFFFF;
                    padding: 10px 12px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #7CB342;
                }
                
                .data-table td {
                    padding: 8px 12px;
                    border: 1px solid #E0E0E0;
                    vertical-align: middle;
                }
                
                .data-table tr:nth-child(even) {
                    background-color: #F9FBE7;
                }
                
                .data-table tr:hover {
                    background-color: #E8F5E9;
                }
                
                .badge {
                    display: inline-block;
                    padding: 2px 12px;
                    border-radius: 12px;
                    font-size: 9pt;
                    font-weight: 600;
                }
                
                .badge-active {
                    background-color: #E8F5E9;
                    color: #2E7D32;
                    border: 1px solid #A5D6A7;
                }
                
                .badge-maintenance {
                    background-color: #FFF3E0;
                    color: #E65100;
                    border: 1px solid #FFCC80;
                }
                
                .badge-inactive {
                    background-color: #FFEBEE;
                    color: #C62828;
                    border: 1px solid #EF9A9A;
                }
                
                .type-badge {
                    display: inline-block;
                    padding: 2px 10px;
                    border-radius: 4px;
                    font-size: 9pt;
                    font-weight: 600;
                }
                
                .type-biodegradable {
                    background-color: #E8F5E9;
                    color: #2E7D32;
                }
                
                .type-plastic {
                    background-color: #E3F2FD;
                    color: #1565C0;
                }
                
                .type-metal {
                    background-color: #F5F5F5;
                    color: #616161;
                }
                
                .type-general {
                    background-color: #FFF3E0;
                    color: #E65100;
                }
                
                .fill-bar {
                    display: inline-block;
                    width: 80px;
                    height: 8px;
                    background-color: #E0E0E0;
                    border-radius: 4px;
                    overflow: hidden;
                    vertical-align: middle;
                    margin-right: 6px;
                }
                
                .fill-bar .fill {
                    height: 100%;
                    border-radius: 4px;
                }
                
                .fill-low .fill { background-color: #8BC34A; }
                .fill-medium .fill { background-color: #FFC107; }
                .fill-high .fill { background-color: #FF9800; }
                .fill-full .fill { background-color: #F44336; }
                
                .report-footer {
                    border-top: 2px solid #E0E0E0;
                    padding-top: 12px;
                    margin-top: 24px;
                    text-align: center;
                    font-size: 9pt;
                    color: #9E9E9E;
                }
                
                .report-footer .footer-line {
                    margin-top: 4px;
                }
            </style>
        </head>
        <body>
        
        <div class="report-header">
            <h1>♻️ CT-ABS</h1>
            <div class="subtitle">Trash Bin Locations Report</div>
            <div class="meta">
                Generated: ' . $stats['generatedAt'] . ' &nbsp;|&nbsp; By: ' . htmlspecialchars($stats['generatedBy']) . '
            </div>
        </div>
        
        <table class="stats-grid">
            <tr>
                <td class="stat-item">
                    <span class="stat-value">' . $stats['totalBins'] . '</span>
                    <span class="stat-label">Total Bins</span>
                </td>
                <td class="stat-item">
                    <span class="stat-value" style="color: #4CAF50;">' . $stats['activeBins'] . '</span>
                    <span class="stat-label">Active</span>
                </td>
                <td class="stat-item">
                    <span class="stat-value" style="color: #FF9800;">' . $stats['maintenanceBins'] . '</span>
                    <span class="stat-label">Maintenance</span>
                </td>
                <td class="stat-item">
                    <span class="stat-value" style="color: #2196F3;">' . number_format($stats['avgFill'], 1) . '%</span>
                    <span class="stat-label">Avg Fill Level</span>
                </td>
            </tr>
        </table>
        
        <div class="section-title">📊 Bin Type Breakdown</div>
        <table class="data-table" style="width: 60%;">
            <thead>
                <tr>
                    <th>Bin Type</th>
                    <th style="text-align: center;">Count</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($stats['typeCounts'] as $type => $count) {
            $html .= '
                <tr>
                    <td><span class="type-badge type-' . $type . '">' . ucfirst($type) . '</span></td>
                    <td style="text-align: center; font-weight: 600;">' . $count . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="section-title">📋 Complete Bin List</div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bin Name</th>
                        <th>Code</th>
                        <th>Location</th>
                        <th>Building</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th style="text-align: center;">Fill Level</th>
                    </tr>
                </thead>
                <tbody>';
        
        if (empty($bins)) {
            $html .= '
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #757575;">
                        <i>No bins found in the system.</i>
                    </td>
                </tr>';
        } else {
            foreach ($bins as $bin) {
                $fillLevel = floatval($bin['fill_level'] ?? 0);
                $fillClass = $fillLevel >= 80 ? 'fill-full' : ($fillLevel >= 50 ? 'fill-high' : ($fillLevel >= 25 ? 'fill-medium' : 'fill-low'));
                $statusClass = $bin['status'] === 'active' ? 'badge-active' : ($bin['status'] === 'maintenance' ? 'badge-maintenance' : 'badge-inactive');
                
                $html .= '
                    <tr>
                        <td style="font-weight: 600;">#' . $bin['bin_id'] . '</td>
                        <td><strong>' . htmlspecialchars($bin['bin_name']) . '</strong></td>
                        <td style="font-family: monospace; color: #757575;">' . htmlspecialchars($bin['bin_code']) . '</td>
                        <td>' . htmlspecialchars($bin['location']) . '</td>
                        <td>' . htmlspecialchars($bin['building'] ?? '-') . '</td>
                        <td><span class="type-badge type-' . $bin['bin_type'] . '">' . ucfirst($bin['bin_type']) . '</span></td>
                        <td><span class="badge ' . $statusClass . '">' . ucfirst($bin['status']) . '</span></td>
                        <td style="text-align: center;">
                            <span class="fill-bar ' . $fillClass . '">
                                <span class="fill" style="width: ' . $fillLevel . '%;"></span>
                            </span>
                            ' . number_format($fillLevel, 0) . '%
                        </td>
                    </tr>';
            }
        }
        
        $html .= '
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 12px 16px; background-color: #F5F5F5; border-left: 4px solid #8BC34A; font-size: 10pt; color: #616161;">
            <strong>📌 Report Notes:</strong>
            <ul style="margin: 6px 0 0 20px; padding-left: 0;">
                <li>Fill levels are measured using ultrasonic sensors and are updated in real-time.</li>
                <li>Bins marked as <strong>Maintenance</strong> are currently unavailable for waste disposal.</li>
                <li>This report is auto-generated and reflects the current state of the system.</li>
            </ul>
        </div>
        
        <div class="report-footer">
            <div>SmartBin v2.0 · Automated Trash Bin Management System</div>
            <div class="footer-line">
                <span>ESP32 · AI Vision · Ultrasonic · Solar-Powered</span>
            </div>
            <div class="footer-line" style="margin-top: 4px; font-size: 8pt; color: #BDBDBD;">
                Report ID: SB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) . '
            </div>
        </div>
        
        </body>
        </html>';
        
        return $html;
    }
}
?>