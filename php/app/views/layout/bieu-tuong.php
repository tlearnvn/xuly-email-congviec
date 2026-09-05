<?php
/** Bộ biểu tượng SVG dùng chung */
$duong = [
    'bd' => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
    'hv' => '<path d="M4 7h16v12H4z"/><path d="M4 7l8 6 8-6"/>',
    'vb' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
    'pl' => '<path d="M4 6h6l4 6h6"/><path d="M4 18h6l2-3"/><circle cx="19" cy="12" r="2"/><circle cx="19" cy="18" r="2"/><path d="M14 18h3"/>',
    'tk' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'dm' => '<path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="1.6"/>',
    'nk' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
    'cd' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'tai' => '<path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
    'xem' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    'tep' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
    'tim' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'quay-lai' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
    'them' => '<path d="M12 5v14M5 12h14"/>',
    'sua' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    'xoa' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
    'ai' => '<rect x="5" y="7" width="14" height="12" rx="3"/><path d="M12 4v3M9 12h.01M15 12h.01M9.5 16h5"/>',
    'ok' => '<path d="m5 13 4 4L19 7"/>',
    'canh' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
];
$d = $duong[$ma ?? ''] ?? '';
?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $d ?></svg>
