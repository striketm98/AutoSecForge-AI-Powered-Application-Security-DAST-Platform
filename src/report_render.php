<?php
/**
 * AutoSecForge — professional report renderer.
 *
 * Produces a fully self-contained HTML document (all CSS inline, no external
 * CDNs or fonts) so the exact same markup renders correctly in three places:
 *   • wkhtmltopdf  → a real .pdf deliverable
 *   • MS Word      → a real .doc deliverable (HTML-based, opens natively)
 *   • the browser  → an on-screen / print preview
 *
 * Usage:
 *   require_once '../src/report_render.php';
 *   $data = asf_get_report_data($pdo, $jobId);   // job + findings + analyst
 *   echo asf_render_report_html($data);          // full <html> document
 */

if (!function_exists('asf_get_report_data')) {

/** Load a scan job, its analyst, and its structured findings. */
function asf_get_report_data(PDO $pdo, int $jobId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT j.*, u.full_name AS analyst, u.email AS analyst_email
           FROM scan_jobs j
           LEFT JOIN users u ON u.id = j.triggered_by
          WHERE j.id = ? LIMIT 1'
    );
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) return null;

    $fstmt = $pdo->prepare(
        'SELECT * FROM findings WHERE scan_job_id = ?
          ORDER BY FIELD(severity,"critical","high","medium","low","info"), id'
    );
    $fstmt->execute([$jobId]);
    $job['findings'] = $fstmt->fetchAll(PDO::FETCH_ASSOC);

    return $job;
}

/** Severity → brand colour. */
function asf_sev_color(string $sev): string
{
    return [
        'critical' => '#b91c1c',
        'high'     => '#ea580c',
        'medium'   => '#d97706',
        'low'      => '#2563eb',
        'info'     => '#64748b',
    ][strtolower($sev)] ?? '#64748b';
}

/** Count findings per severity. */
function asf_sev_counts(array $findings): array
{
    $c = ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0,'info'=>0];
    foreach ($findings as $f) {
        $s = strtolower($f['severity'] ?? 'info');
        if (isset($c[$s])) $c[$s]++;
    }
    return $c;
}

/** Overall risk rating derived from the worst-present severity. */
function asf_overall_risk(array $counts): array
{
    if ($counts['critical'] > 0) return ['Critical', '#b91c1c'];
    if ($counts['high']     > 0) return ['High',     '#ea580c'];
    if ($counts['medium']   > 0) return ['Medium',   '#d97706'];
    if ($counts['low']      > 0) return ['Low',      '#2563eb'];
    return ['Informational', '#64748b'];
}

function asf_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Render the full report document.
 * $opts: ['for_word' => bool]  (adds the Office namespace for .doc export)
 */
function asf_render_report_html(array $job, array $opts = []): string
{
    $forWord  = !empty($opts['for_word']);
    $findings = $job['findings'] ?? [];
    $counts   = asf_sev_counts($findings);
    $total    = array_sum($counts);
    [$riskLabel, $riskColor] = asf_overall_risk($counts);

    $target   = asf_e($job['target'] ?? '—');
    $types    = asf_e($job['scan_types'] ?? '—');
    $status   = asf_e(ucfirst($job['status'] ?? 'completed'));
    $model    = asf_e($job['model'] ?? '—');
    $analyst  = asf_e($job['analyst'] ?? 'AutoSecForge');
    $date     = asf_e(substr($job['created_at'] ?? date('Y-m-d H:i'), 0, 16));
    $reportId = 'ASF-' . str_pad((string)($job['id'] ?? 0), 6, '0', STR_PAD_LEFT);
    $analysis = nl2br(asf_e($job['analysis'] ?? '(No AI analysis recorded.)'));
    $rawOut   = asf_e($job['raw_output'] ?? '(No raw output recorded.)');

    $htmlNs = $forWord
        ? '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
        : '<html lang="en">';

    // ── Severity summary chips ────────────────────────────────────
    $chips = '';
    foreach ($counts as $sev => $n) {
        $col = asf_sev_color($sev);
        $chips .= '<td style="text-align:center;padding:0;">'
            . '<div style="border:1px solid #e5e7eb;border-top:3px solid ' . $col . ';border-radius:6px;padding:10px 4px;margin:3px;">'
            . '<div style="font-size:22px;font-weight:700;color:' . $col . ';">' . $n . '</div>'
            . '<div style="font-size:9px;letter-spacing:.5px;text-transform:uppercase;color:#6b7280;">' . $sev . '</div>'
            . '</div></td>';
    }

    // ── Findings summary table ────────────────────────────────────
    $rows = '';
    if ($findings) {
        $i = 0;
        foreach ($findings as $f) {
            $i++;
            $col = asf_sev_color($f['severity'] ?? 'info');
            $rows .= '<tr>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;font-size:10px;color:#6b7280;">' . $i . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;font-size:11px;color:#111827;">' . asf_e($f['title']) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;text-align:center;">'
                .   '<span style="background:' . $col . ';color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;padding:2px 7px;border-radius:10px;">' . asf_e($f['severity']) . '</span>'
                . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;font-size:10px;color:#374151;text-align:center;">' . asf_e($f['cvss_score'] ?: '—') . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;font-size:10px;color:#374151;">' . asf_e($f['cwe_id'] ?: '') . ' ' . asf_e($f['cve_id'] ?: '') . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f3;font-size:10px;color:#6b7280;word-break:break-all;">' . asf_e($f['affected_url'] ?: '—') . '</td>'
                . '</tr>';
        }
    } else {
        $rows = '<tr><td colspan="6" style="padding:18px;text-align:center;color:#6b7280;font-size:11px;">'
              . 'No structured findings were extracted for this assessment. See the AI triage narrative and raw output below.</td></tr>';
    }

    // ── Detailed findings ─────────────────────────────────────────
    $details = '';
    $i = 0;
    foreach ($findings as $f) {
        $i++;
        $col = asf_sev_color($f['severity'] ?? 'info');
        $meta = [];
        if (!empty($f['cvss_score'])) $meta[] = 'CVSS ' . asf_e($f['cvss_score']);
        if (!empty($f['cwe_id']))     $meta[] = asf_e($f['cwe_id']);
        if (!empty($f['cve_id']))     $meta[] = asf_e($f['cve_id']);
        $metaStr = $meta ? ' &nbsp;·&nbsp; ' . implode(' &nbsp;·&nbsp; ', $meta) : '';
        $details .= '<div style="margin-bottom:14px;border:1px solid #e5e7eb;border-left:4px solid ' . $col . ';border-radius:6px;padding:11px 14px;">'
            . '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
            .   '<td style="font-size:12px;font-weight:700;color:#111827;">' . $i . '. ' . asf_e($f['title']) . '</td>'
            .   '<td style="text-align:right;"><span style="background:' . $col . ';color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;padding:2px 8px;border-radius:10px;">' . asf_e($f['severity']) . '</span></td>'
            . '</tr></table>'
            . '<div style="font-size:9px;color:#9ca3af;margin:2px 0 7px;">' . trim(asf_e($f['affected_url'] ?: 'Scope: ' . $target)) . $metaStr . '</div>';
        if (!empty($f['description'])) {
            $details .= '<div style="font-size:11px;color:#374151;line-height:1.55;margin-bottom:6px;">' . nl2br(asf_e($f['description'])) . '</div>';
        }
        if (!empty($f['remediation'])) {
            $details .= '<div style="font-size:10.5px;color:#065f46;background:#ecfdf5;border-radius:5px;padding:7px 10px;line-height:1.5;">'
                      . '<strong>Remediation:</strong> ' . nl2br(asf_e($f['remediation'])) . '</div>';
        }
        $details .= '</div>';
    }
    if (!$details) $details = '<div style="font-size:11px;color:#6b7280;">No per-finding detail available.</div>';

    $sectionTitle = function (string $t) {
        return '<div style="margin:0 0 12px;padding-bottom:6px;border-bottom:2px solid #6366f1;font-size:14px;font-weight:700;color:#312e81;letter-spacing:.3px;">' . $t . '</div>';
    };

    // ── Document ──────────────────────────────────────────────────
    return '<!DOCTYPE html>' . $htmlNs . '<head><meta charset="utf-8">'
    . '<title>AutoSecForge Report ' . $reportId . '</title>'
    . '<style>'
    . 'body{font-family:"Segoe UI",Arial,Helvetica,sans-serif;color:#1f2937;margin:0;}'
    . '.page{page-break-after:always;}'
    . 'table{border-collapse:collapse;}'
    . '@page{margin:18mm 16mm;}'
    . '</style></head><body>'

    // ── Cover page ─────────────────────────────────────────────
    . '<div class="page" style="padding:0;">'
    .   '<div style="background:#312e81;background:linear-gradient(135deg,#4338ca,#6d28d9);color:#fff;padding:48px 44px 40px;">'
    .     '<table width="100%"><tr>'
    .       '<td style="font-size:13px;font-weight:700;letter-spacing:2px;">AUTOSECFORGE</td>'
    .       '<td style="text-align:right;font-size:10px;color:#c7d2fe;">' . $reportId . '</td>'
    .     '</tr></table>'
    .     '<div style="height:60px;"></div>'
    .     '<div style="font-size:13px;letter-spacing:3px;color:#c7d2fe;">SECURITY ASSESSMENT</div>'
    .     '<div style="font-size:34px;font-weight:800;margin:6px 0 0;line-height:1.1;">Penetration Test<br>&amp; Triage Report</div>'
    .     '<div style="height:28px;"></div>'
    .     '<div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:8px;padding:10px 18px;font-size:15px;font-weight:600;">' . $target . '</div>'
    .   '</div>'
    .   '<div style="padding:34px 44px;">'
    .     '<table width="100%" style="font-size:12px;color:#374151;">'
    .       '<tr><td style="padding:7px 0;width:34%;color:#6b7280;">Overall Risk</td><td style="padding:7px 0;"><span style="background:' . $riskColor . ';color:#fff;font-weight:700;padding:3px 12px;border-radius:12px;font-size:11px;">' . $riskLabel . '</span></td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">Total Findings</td><td style="padding:7px 0;font-weight:600;">' . $total . '</td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">Scan Modules</td><td style="padding:7px 0;">' . $types . '</td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">Assessment Date</td><td style="padding:7px 0;">' . $date . '</td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">Analyst</td><td style="padding:7px 0;">' . $analyst . '</td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">AI Triage Model</td><td style="padding:7px 0;">' . $model . '</td></tr>'
    .       '<tr><td style="padding:7px 0;color:#6b7280;">Status</td><td style="padding:7px 0;">' . $status . '</td></tr>'
    .     '</table>'
    .     '<div style="margin-top:28px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;font-size:10px;color:#991b1b;">'
    .       '<strong>CONFIDENTIAL.</strong> This document contains sensitive security information about ' . $target
    .       ' and is intended solely for authorised recipients. Unauthorised disclosure, copying, or distribution is prohibited.'
    .     '</div>'
    .   '</div>'
    . '</div>'

    // ── Body page ──────────────────────────────────────────────
    . '<div style="padding:4px 0;">'

    .   $sectionTitle('1. Executive Summary')
    .   '<div style="font-size:11.5px;line-height:1.65;color:#374151;margin-bottom:10px;">'
    .     'A security assessment of <strong>' . $target . '</strong> was performed using the '
    .     $types . ' module(s). The assessment surfaced <strong>' . $total . '</strong> finding(s), '
    .     'yielding an overall risk rating of <strong style="color:' . $riskColor . ';">' . $riskLabel . '</strong>. '
    .     'Severity distribution: ' . $counts['critical'] . ' critical, ' . $counts['high'] . ' high, '
    .     $counts['medium'] . ' medium, ' . $counts['low'] . ' low, ' . $counts['info'] . ' informational.'
    .   '</div>'

    .   '<table width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 22px;"><tr>' . $chips . '</tr></table>'

    .   $sectionTitle('2. Findings Overview')
    .   '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">'
    .     '<thead><tr style="background:#f3f4f6;">'
    .       '<th style="padding:7px 8px;text-align:left;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">#</th>'
    .       '<th style="padding:7px 8px;text-align:left;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">Finding</th>'
    .       '<th style="padding:7px 8px;text-align:center;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">Severity</th>'
    .       '<th style="padding:7px 8px;text-align:center;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">CVSS</th>'
    .       '<th style="padding:7px 8px;text-align:left;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">Refs</th>'
    .       '<th style="padding:7px 8px;text-align:left;font-size:9px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;">Affected</th>'
    .     '</tr></thead><tbody>' . $rows . '</tbody>'
    .   '</table>'

    .   $sectionTitle('3. Detailed Findings &amp; Remediation')
    .   $details

    .   '<div style="height:18px;"></div>'
    .   $sectionTitle('4. AI Triage Narrative')
    .   '<div style="font-size:11px;line-height:1.65;color:#374151;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:22px;">' . $analysis . '</div>'

    .   $sectionTitle('Appendix A — Raw Scan Output')
    .   '<pre style="white-space:pre-wrap;word-break:break-all;font-family:Consolas,\'Courier New\',monospace;font-size:8.5px;line-height:1.5;color:#111827;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:12px;">' . $rawOut . '</pre>'

    .   '<div style="margin-top:26px;padding-top:10px;border-top:1px solid #e5e7eb;font-size:9px;color:#9ca3af;text-align:center;">'
    .     'AutoSecForge Pro · ' . $reportId . ' · Generated ' . date('Y-m-d H:i') . ' · CONFIDENTIAL'
    .   '</div>'
    . '</div>'

    . '</body></html>';
}

} // function_exists guard
