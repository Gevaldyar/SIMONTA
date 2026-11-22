<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use App\Models\Website;
use App\Services\DomainSslNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainSslNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(): Website
    {
        $opd = Opd::create([
            'name' => 'OPD Notification Test',
            'is_active' => true,
        ]);

        return $opd->websites()->create([
            'name' => 'Portal Notification Test',
            'url' => 'https://example.id',
            'is_active' => true,
            'monitoring_enabled' => true,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    private function createDomainCheck(
        Website $website,
        string $status = 'valid',
        ?int $days = null,
        $expiresAt = null
    ) {
        return $website->domainRegistrationChecks()->create([
            'domain' => 'example.id',
            'status' => $status,
            'expires_at' => $expiresAt,
            'days_remaining' => $days,
            'nameservers' => [],
            'checked_at' => now(),
        ]);
    }

    private function createSslCheck(
        Website $website,
        string $status = 'valid',
        string $verification = 'passed',
        ?int $days = null,
        $validTo = null,
        ?string $errorMessage = null
    ) {
        return $website->sslCertificateChecks()->create([
            'hostname' => 'example.id',
            'status' => $status,
            'verification' => $verification,
            'valid_to' => $validTo,
            'days_remaining' => $days,
            'checked_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    private function notificationTypes(User $user): array
    {
        return $user->notifications()
            ->get()
            ->map(fn ($notification) => $notification->data['type'] ?? null)
            ->all();
    }

    public function test_domain_and_ssl_expiry_warnings_are_sent_once(): void
    {
        $user = User::factory()->create();
        $website = $this->createWebsite();

        $domainCheck = $this->createDomainCheck(
            $website,
            'expiring_soon',
            30,
            now()->addDays(30)
        );

        $sslCheck = $this->createSslCheck(
            $website,
            'expiring_soon',
            'passed',
            14,
            now()->addDays(14)
        );

        $service = app(DomainSslNotificationService::class);

        $service->notifyForChecks($domainCheck, $sslCheck);

        $this->assertDatabaseCount('notifications', 2);

        $this->assertEqualsCanonicalizing(
            [
                'domain_expiry_warning',
                'ssl_expiry_warning',
            ],
            $this->notificationTypes($user)
        );

        $service->notifyForChecks($domainCheck, $sslCheck);

        // Event yang sama tidak dikirim dua kali.
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_expired_domain_and_ssl_generate_danger_notifications(): void
    {
        $user = User::factory()->create();
        $website = $this->createWebsite();

        $domainCheck = $this->createDomainCheck(
            $website,
            'expired',
            -1,
            now()->subDay()
        );

        $sslCheck = $this->createSslCheck(
            $website,
            'expired',
            'passed',
            -1,
            now()->subDay()
        );

        app(DomainSslNotificationService::class)
            ->notifyForChecks($domainCheck, $sslCheck);

        $notifications = $user->notifications()->get();

        $this->assertCount(2, $notifications);

        $this->assertEqualsCanonicalizing(
            [
                'domain_expired',
                'ssl_expired',
            ],
            $this->notificationTypes($user)
        );

        foreach ($notifications as $notification) {
            $this->assertSame(
                'danger',
                $notification->data['severity']
            );
        }
    }

    public function test_tls_failure_is_notified_once_and_recovery_is_detected(): void
    {
        $user = User::factory()->create();
        $website = $this->createWebsite();

        $domainCheck = $this->createDomainCheck(
            $website,
            'unavailable'
        );

        $service = app(DomainSslNotificationService::class);

        // Kegagalan pertama.
        $failedCheck = $this->createSslCheck(
            $website,
            'verification_failed',
            'failed',
            null,
            null,
            'Certificate verify failed.'
        );

        $service->notifyForChecks($domainCheck, $failedCheck);

        $this->assertSame(
            ['ssl_verification_failed'],
            $this->notificationTypes($user)
        );

        // Kegagalan berulang tidak boleh mengirim notifikasi baru.
        $failedAgain = $this->createSslCheck(
            $website,
            'verification_failed',
            'failed',
            null,
            null,
            'Certificate verify failed.'
        );

        $service->notifyForChecks($domainCheck, $failedAgain);

        $this->assertDatabaseCount('notifications', 1);

        // Sertifikat berhasil diverifikasi kembali.
        $restoredCheck = $this->createSslCheck(
            $website,
            'valid',
            'passed',
            180,
            now()->addDays(180)
        );

        $service->notifyForChecks($domainCheck, $restoredCheck);

        $this->assertEqualsCanonicalizing(
            [
                'ssl_verification_failed',
                'ssl_verification_restored',
            ],
            $this->notificationTypes($user)
        );

        // Kegagalan baru setelah pemulihan merupakan event baru.
        $newFailure = $this->createSslCheck(
            $website,
            'verification_failed',
            'failed',
            null,
            null,
            'Hostname mismatch.'
        );

        $service->notifyForChecks($domainCheck, $newFailure);

        $this->assertDatabaseCount('notifications', 3);
    }
}
