<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\User;
use App\Notifications\SimontaEventNotification;
use Throwable;

class ScanNotificationService
{
    /**
     * Membuat notifikasi berdasarkan hasil pemindaian.
     */
    public function notifyForScan(Scan $scan): void
    {
        try {
            $scan->loadMissing([
                'website' => fn ($query) => $query
                    ->withTrashed()
                    ->with([
                        'opd' => fn ($opdQuery) => $opdQuery
                            ->withTrashed(),
                    ]),
                'technologyChanges.technology',
            ]);

            $website = $scan->website;

            if (! $website) {
                return;
            }

            $websiteName = $website->name ?: $website->url;
            $opdName = $website->opd?->name ?? 'OPD tidak diketahui';

            // 1. Pemindaian gagal.
            if ($scan->status === 'failed') {
                $this->sendOnce(
                    eventKey: "scan:{$scan->id}:failed",
                    payload: [
                        'type' => 'scan_failed',
                        'severity' => 'danger',
                        'title' => 'Pemindaian website gagal',
                        'message' => "Pemindaian {$websiteName} milik {$opdName} gagal dilakukan.",
                        'event_key' => "scan:{$scan->id}:failed",
                        'scan_id' => $scan->id,
                        'website_id' => $website->id,
                        'website_name' => $websiteName,
                        'opd_name' => $opdName,
                        'http_status_code' => $scan->http_status_code,
                        'error_message' => $scan->error_message,
                        'url' => '/history',
                    ]
                );

                return;
            }

            // Hanya proses scan yang sudah selesai.
            if ($scan->status !== 'completed') {
                return;
            }

            // 2. Security Assessment Notification.
$assessment = $scan->metadata['security_assessment'] ?? null;

if (
    is_array($assessment)
    && in_array(
        $assessment['status'] ?? null,
        ['review', 'high'],
        true
    )
) {
    $securityStatus = $assessment['status'];
    $riskScore = $assessment['risk_score'] ?? null;

    $eventKey = "scan:{$scan->id}:security-assessment";

    $isHighRisk = $securityStatus === 'high';

    $findingTitles = collect(
        $assessment['findings'] ?? []
    )
        ->pluck('title')
        ->filter()
        ->take(3)
        ->implode(', ');

    $message = "{$websiteName} milik {$opdName} "
        . "mendapatkan hasil Security Assessment {$securityStatus}";

    if ($riskScore !== null) {
        $message .= " dengan skor risiko {$riskScore}/100";
    }

    if ($findingTitles !== '') {
        $message .= ". Indikator: {$findingTitles}";
    }

    $message .= '. Silakan periksa hasil Security Assessment.';

    $this->sendOnce(
        eventKey: $eventKey,
        payload: [
            'type' => 'security_risk_detected',
            'severity' => $isHighRisk ? 'danger' : 'warning',
            'title' => $isHighRisk
                ? 'Indikator keamanan berisiko tinggi'
                : 'Indikator keamanan perlu ditinjau',
            'message' => $message,
            'event_key' => $eventKey,
            'scan_id' => $scan->id,
            'website_id' => $website->id,
            'website_name' => $websiteName,
            'opd_name' => $opdName,
            'security_status' => $securityStatus,
            'risk_score' => $riskScore,
            'findings' => array_slice(
                $assessment['findings'] ?? [],
                0,
                10
            ),
            'url' => '/security',
        ]
    );
}

            // 2. Perubahan teknologi.
            foreach ($scan->technologyChanges as $change) {
                $eventKey = "scan:{$scan->id}:change:{$change->id}";

                $technologyName = $change->technology_name;

                $type = match ($change->change_type) {
                    'added' => 'technology_added',
                    'removed' => 'technology_removed',
                    'version_changed' => 'technology_version_changed',
                    default => null,
                };

                if ($type === null) {
                    continue;
                }

                $title = match ($change->change_type) {
                    'added' => 'Teknologi baru terdeteksi',
                    'removed' => 'Teknologi tidak lagi terdeteksi',
                    'version_changed' => 'Versi teknologi berubah',
                    default => 'Perubahan teknologi',
                };

                $severity = match ($change->change_type) {
                    'added' => 'info',
                    'removed', 'version_changed' => 'warning',
                    default => 'info',
                };

                $message = match ($change->change_type) {
                    'added' => "{$technologyName} baru terdeteksi pada {$websiteName}.",
                    'removed' => "{$technologyName} tidak lagi terdeteksi pada {$websiteName}.",
                    'version_changed' => "{$technologyName} berubah dari versi "
                        . ($change->previous_version ?? 'tidak diketahui')
                        . ' menjadi '
                        . ($change->current_version ?? 'tidak diketahui')
                        . " pada {$websiteName}.",
                    default => '',
                };

                $this->sendOnce(
                    eventKey: $eventKey,
                    payload: [
                        'type' => $type,
                        'severity' => $severity,
                        'title' => $title,
                        'message' => $message,
                        'event_key' => $eventKey,
                        'scan_id' => $scan->id,
                        'technology_change_id' => $change->id,
                        'technology_id' => $change->technology_id,
                        'technology_name' => $technologyName,
                        'technology_slug' => $change->technology_slug,
                        'change_type' => $change->change_type,
                        'previous_version' => $change->previous_version,
                        'current_version' => $change->current_version,
                        'website_id' => $website->id,
                        'website_name' => $websiteName,
                        'opd_name' => $opdName,
                        'url' => '/technology-changes',
                    ]
                );
            }

            // 3. Website memberikan respons HTTP non-2xx.
            // 3. Website memberikan respons HTTP error (400–599).
$httpStatus = $scan->http_status_code;

if (
    $httpStatus !== null
    && $httpStatus >= 400
    && $httpStatus <= 599
) {
    $eventKey = "scan:{$scan->id}:http:{$httpStatus}";

    $severity = $httpStatus >= 500
        ? 'danger'
        : 'warning';

    $title = $httpStatus >= 500
        ? 'Website mengalami server error'
        : 'Website mengalami client error';

    $message = "{$websiteName} milik {$opdName} "
        . "memberikan respons HTTP {$httpStatus}. "
        . ($httpStatus >= 500
            ? 'Server mengalami kesalahan saat memproses permintaan.'
            : 'Permintaan tidak dapat dipenuhi oleh server.');

    $this->sendOnce(
        eventKey: $eventKey,
        payload: [
            'type' => 'http_non_success',
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'event_key' => $eventKey,
            'scan_id' => $scan->id,
            'website_id' => $website->id,
            'website_name' => $websiteName,
            'opd_name' => $opdName,
            'http_status_code' => $httpStatus,
            'url' => '/history',
        ]
    );
}
        } catch (Throwable $exception) {
            // Kegagalan notifikasi tidak boleh menggagalkan scan.
            report($exception);
        }
    }

    /**
     * Mengirim notifikasi kepada seluruh pengguna yang terdaftar,
     * tanpa mengirim event yang sama lebih dari satu kali per pengguna.
     */
    private function sendOnce(string $eventKey, array $payload): void
    {
        User::query()
            ->select('id')
            ->chunkById(100, function ($users) use ($eventKey, $payload) {
                foreach ($users as $user) {
                    try {
                        $alreadySent = $user->notifications()
                            ->where(
                                'data->event_key',
                                $eventKey
                            )
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        $user->notify(
                            new SimontaEventNotification($payload)
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });
    }
}
