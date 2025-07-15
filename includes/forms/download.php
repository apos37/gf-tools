<?php
$allowed_files = [
    'contact-form.json',
    'registration-form.json',
    'account-form.json',
    'password-change-form.json',
    'login-form.json',
    'password-reset-form.json',
];

if ( !isset( $_GET[ 'file' ] ) ) {
    http_response_code(400);
    exit( 'Missing file parameter.' );
}

if ( !function_exists( 'wp_unslash' ) ) {
    require_once dirname( __FILE__, 6 ) . '/wp-load.php';
}

if ( ! isset( $_GET[ '_wpnonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ '_wpnonce' ] ) ), 'gfat_form_download' ) ) {
    http_response_code(403);
    exit( 'Invalid nonce.' );
}

if ( ! current_user_can( 'manage_options' ) ) {
    http_response_code(403);
    exit( 'Access denied.' );
}

$file_raw = wp_unslash( $_GET[ 'file' ] );
$file     = basename( sanitize_file_name( $file_raw ) );

if ( !in_array( $file, $allowed_files, true ) ) {
    http_response_code(403);
    exit( 'Unauthorized file.' );
}

$file_path = __DIR__ . '/' . $file;

if ( !file_exists( $file_path ) ) {
    http_response_code(404);
    exit( 'File not found.' );
}

header( 'Content-Description: File Transfer' );
header( 'Content-Type: application/json' );
header( 'Content-Disposition: attachment; filename="' . $file . '"' );
header( 'Expires: 0' );
header( 'Cache-Control: must-revalidate' );
header( 'Pragma: public' );
header( 'Content-Length: ' . filesize( $file_path ) );

readfile( $file_path );
exit;