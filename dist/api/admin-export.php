<?php
/** Admin: export CSV — utilizatori sau programări. Deschide direct în Excel. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_admin();

$what = $_GET['what'] ?? 'users';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="export-' . $what . '-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
// BOM pentru diacritice corecte în Excel
fwrite($out, "\xEF\xBB\xBF");

if ($what === 'bookings') {
    fputcsv($out, ['Ora', 'Data', 'Status oră', 'Copil', 'Clasa', 'Părinte', 'Email părinte', 'Status programare', 'Prezență'], ';');
    $rows = db()->query(
        'SELECT s.title, s.starts_at, s.status AS sess_status, c.first_name, c.grade,
                u.full_name, u.email, b.status AS b_status, b.attended
         FROM bookings b
         JOIN class_sessions s ON s.id = b.session_id
         JOIN children c ON c.id = b.child_id
         JOIN users u ON u.id = b.user_id
         ORDER BY s.starts_at DESC'
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['title'], $r['starts_at'], $r['sess_status'], $r['first_name'], $r['grade'],
            $r['full_name'], $r['email'], $r['b_status'],
            $r['attended'] === null ? '' : ($r['attended'] ? 'prezent' : 'absent'),
        ], ';');
    }
} else {
    fputcsv($out, ['ID', 'Nume', 'Email', 'Activat', 'Blocat', 'Noutăți', 'Nr. copii', 'Creat la'], ';');
    $rows = db()->query(
        'SELECT u.*, (SELECT COUNT(*) FROM children c WHERE c.user_id = u.id) AS nr_copii
         FROM users u ORDER BY u.created_at'
    );
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['full_name'], $r['email'],
            $r['email_verified_at'] !== null ? 'da' : 'nu',
            $r['blocked_at'] !== null ? 'da' : 'nu',
            $r['marketing_optin'] ? 'da' : 'nu',
            $r['nr_copii'], $r['created_at'],
        ], ';');
    }
}

fclose($out);
exit;
