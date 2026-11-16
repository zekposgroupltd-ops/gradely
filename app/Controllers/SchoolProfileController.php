<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, View};
use App\Services\Uploads;

/** School administrator: school information and logo. */
final class SchoolProfileController extends BaseController
{
    public function show(): void
    {
        View::render('school/profile', [
            'title' => 'School profile',
            's' => DB::one('SELECT * FROM schools WHERE id = ?', [$this->sid()]),
        ]);
    }

    public function update(): void
    {
        $name = $this->text('name');
        $email = strtolower((string) $this->text('email'));
        $phone = $this->text('phone', 40);
        $address = $this->text('address', 255);
        $website = $this->text('website');
        if ($name === null || $name === '' || $phone === null || $address === null || $website === null) {
            $this->fail('The school name is required and some values are too long.', '/school/profile');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('The email address is not valid.', '/school/profile');
        }
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $this->fail('The website address is not valid.', '/school/profile');
        }
        DB::run(
            'UPDATE schools SET name = ?, email = ?, phone = ?, address = ?, website = ? WHERE id = ?',
            [$name, $email ?: null, $phone ?: null, $address ?: null, $website ?: null, $this->sid()]
        );
        AuditLog::record('school.profile_updated', 'school', $this->sid());
        $this->ok('School profile saved.', '/school/profile');
    }

    public function logo(): void
    {
        try {
            $rel = Uploads::saveLogo($_FILES['logo'] ?? [], $this->sid());
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), '/school/profile');
        }
        DB::run('UPDATE schools SET logo_path = ? WHERE id = ?', [$rel, $this->sid()]);
        AuditLog::record('school.logo_updated', 'school', $this->sid());
        $this->ok('Logo updated.', '/school/profile');
    }
}
