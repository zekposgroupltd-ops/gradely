<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{DB, Http, View};
use App\Services\{ReportService, ResultVerification};

/** Public, privacy-limited authenticity verification for published reports. */
final class VerificationController
{
    public function manual(): void
    {
        $code = trim(Http::input('code'));
        if (preg_match('~(?:/verify/)?([a-f0-9]{32})$~i', $code, $m)) {
            Http::redirect('/verify/' . strtolower($m[1]));
        }
        View::render('portal/verify', ['title' => 'Verify result', 'verified' => false, 'details' => null, 'message' => 'Enter a valid 32-character verification code or scan the barcode.'], 'portal');
    }

    public function show(string $token): void
    {
        $record = ResultVerification::find(strtolower($token));
        $report = null;
        $verified = false;
        $details = null;
        $message = 'This verification code is invalid, expired or has been withdrawn.';
        if ($record !== null && $record['revoked_at'] === null) {
            $report = ReportService::build((int) $record['school_id'], (int) $record['term_id'], (int) $record['student_id'], true);
            if ($report !== null
                && isset($report['verification']['token'])
                && hash_equals((string) $record['token'], (string) $report['verification']['token'])
                && hash_equals((string) $record['snapshot_hash'], ResultVerification::snapshot($report))) {
                $verified = true;
                $details = [
                    'school' => $report['school']['name'],
                    'student_id' => $report['student']['admission_no'],
                    'student_name' => trim($report['student']['first_name'] . ' ' . $report['student']['other_name'] . ' ' . $report['student']['last_name']),
                    'session' => $report['term']['session_name'],
                    'term' => $report['term']['term_name'],
                ];
                $message = 'The published school record matches this verification code.';
            } else {
                $message = 'The result no longer matches the published record. Verification failed.';
            }
        }
        View::render('portal/verify', ['title' => 'Result verification', 'verified' => $verified, 'details' => $details, 'message' => $message], 'portal');
    }
}
